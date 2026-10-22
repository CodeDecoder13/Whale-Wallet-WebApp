<?php

namespace App\Http\Controllers;

use App\Actions\CreateTransaction;
use App\Actions\HandleChatMessage;
use App\Actions\ManageByChat;
use App\Actions\PayRecurring;
use App\Actions\StoreReceipt;
use App\Contracts\AiProvider;
use App\Enums\RecurringKind;
use App\Enums\TransactionSource;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\IncomeSource;
use App\Models\Receipt;
use App\Models\RecurringItem;
use App\Services\Ai\AiErrors;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ChatController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('chat/Index', $this->history($request));
    }

    /** The same data as the page, for the floating chat window. */
    public function recent(Request $request): JsonResponse
    {
        return response()->json($this->history($request));
    }

    private function history(Request $request): array
    {
        $thread = $request->integer('thread') ? ChatThread::findOrFail($request->integer('thread')) : ChatThread::latest('id')->first();
        $incomeReceipts = Receipt::where('purpose', 'income')->pluck('id');

        return [
            'consented' => (bool) $request->user()->ai_consent_at,
            'thread_id' => $thread?->id,
            // history for the side panel: newest activity first; a deleted chat is soft-deleted and no longer listed
            'threads' => ChatThread::latest('updated_at')->latest('id')->limit(50)->get(['id', 'title', 'updated_at']),
            'messages' => $thread?->messages()->latest('id')->limit(50)->get(['id', 'role', 'content', 'draft_json', 'receipt_id'])
                ->each(fn ($m) => $m->income = $m->receipt_id && $incomeReceipts->contains($m->receipt_id))
                ->reverse()->values() ?? [],
        ];
    }

    /** A fresh, empty chat. */
    public function newThread(Request $request): JsonResponse
    {
        return response()->json($request->user()->chatThreads()->create()->only(['id', 'title']), 201);
    }

    public function renameThread(Request $request, ChatThread $thread): JsonResponse
    {
        $thread->update($request->validate(['title' => ['required', 'string', 'max:80']]));

        return response()->json($thread->only(['id', 'title']));
    }

    /** Soft delete: the chat leaves the history list; its rows stay until the user wipes everything in Settings. */
    public function deleteThread(ChatThread $thread): JsonResponse
    {
        $thread->delete();

        return response()->json(null, 204);
    }

    /** Returns { reply, draft?, quick_replies? }. An attached image goes through the receipt pipeline. */
    public function messages(Request $request, HandleChatMessage $chat, StoreReceipt $store): JsonResponse
    {
        $data = $request->validate([
            'text' => ['required_without:image', 'nullable', 'string', 'max:500'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:max_width=8000,max_height=8000'],
            'thread_id' => ['nullable', 'integer'],
        ]);
        $user = $request->user();
        $thread = isset($data['thread_id']) ? ChatThread::findOrFail($data['thread_id']) : (ChatThread::latest('id')->first() ?? $user->chatThreads()->create());

        if ($request->hasFile('image')) {
            // a screenshot sent right after the salary prompt (or captioned "salary") is a salary proof
            $last = $thread->messages()->where('role', 'assistant')->latest('id')->first()?->draft_json;
            $income = HandleChatMessage::isSalary($data['text'] ?? '') || ($last['kind'] ?? null) === 'income';
            $receipt = $store->handle($user, $request->file('image'), $income ? 'income' : 'expense');
            $claimed = ($last['kind'] ?? null) === 'income' ? ($last['amount'] ?? null) : null;
            $claimed && $receipt->update(['claimed_amount' => $claimed]);
            $reply = $income
                ? 'Got your proof. I am reading it now'.($claimed ? ' and will check it against ₱'.number_format($claimed / 100, 2) : '').'; confirm the reference number, date and amount from Add income on your dashboard.'
                : 'Got your receipt. I am reading it now; you check it before anything is saved.';
            $thread->messages()->create(['role' => 'user', 'content' => $data['text'] ?? 'Sent a receipt', 'receipt_id' => $receipt->id]);
            $message = $thread->messages()->create(['role' => 'assistant', 'content' => $reply, 'receipt_id' => $receipt->id]);

            return response()->json(['id' => $message->id, 'reply' => $reply, 'receipt_id' => $receipt->id, 'income' => $income, 'thread_id' => $thread->id]);
        }

        try {
            $out = $chat->handle($user, $thread, $data['text']);
        } catch (Throwable $e) {
            report($e); // the client only gets a fixed sentence, never the provider's text

            return response()->json(['message' => AiErrors::message($e), 'manual_url' => route('transactions.index')], AiErrors::isQuota($e) ? 429 : 503);
        }

        return response()->json($out + ['thread_id' => $thread->id]);
    }

    /** Voice to text for the chat box: the browser sends a short recording, the text goes back to be edited and sent. */
    public function transcribe(Request $request, AiProvider $ai): JsonResponse
    {
        $request->validate(['audio' => ['required', 'file', 'max:5120', 'mimetypes:audio/webm,video/webm,audio/mp4,video/mp4,audio/ogg,audio/mpeg,audio/wav,audio/x-wav']]);
        $file = $request->file('audio');

        try {
            $text = $ai->transcribe($file->getRealPath(), explode(';', (string) $file->getMimeType())[0]);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => AiErrors::message($e)], AiErrors::isQuota($e) ? 429 : 503);
        }

        return response()->json(['text' => mb_substr($text, 0, 500)]);
    }

    /** The only place a chat draft becomes a transaction. */
    public function confirm(ChatMessage $message, CreateTransaction $create): JsonResponse
    {
        $done = null;
        $id = DB::transaction(function () use ($message, $create, &$done) {
            $message = ChatMessage::lockForUpdate()->findOrFail($message->id);
            $d = $message->draft_json;
            abort_unless($d && ! $d['missing'] && empty($d['saved']) && ($d['kind'] ?? null) !== 'dues', 422, 'There is no complete draft to save here.');

            // typed income: saved without proof, like Add income entered by hand
            if (($d['kind'] ?? null) === 'income') {
                $income = IncomeSource::create([
                    'name' => 'Income', 'amount' => $d['amount'], 'received_on' => $d['occurred_at'], 'month' => substr($d['occurred_at'], 0, 7),
                ]);
                $message->update(['draft_json' => $d + ['saved' => $income->id, 'done' => 'Income recorded.']]);

                return $income->id;
            }

            // add, edit, cancel, renew or delete from chat; the target is checked again inside apply()
            if (($d['kind'] ?? null) === 'manage') {
                $done = app(ManageByChat::class)->apply($d);
                $message->update(['draft_json' => $d + ['saved' => $d['target_id'] ?: 1, 'done' => $done]]);

                return $d['target_id'] ?: 1;
            }

            if (($d['kind'] ?? null) === 'loan') {
                $loan = RecurringItem::create([
                    'name' => $d['merchant'], 'amount' => $d['amount'], 'cadence' => 'monthly', 'kind' => RecurringKind::Debt,
                    'installments' => $d['payments'], 'next_due_on' => $d['occurred_at'],
                ]);
                $message->update(['draft_json' => $d + ['saved' => $loan->id]]);

                return $loan->id;
            }

            if (! empty($d['recurring_id'])) {
                $transaction = app(PayRecurring::class)->handle(
                    RecurringItem::findOrFail($d['recurring_id']), $d['card_id'], $d['amount'], $d['occurred_at'], TransactionSource::Chat,
                );
                $message->update(['draft_json' => $d + ['saved' => $transaction->id]]);

                return $transaction->id;
            }

            $transaction = $create->handle([
                'merchant' => $d['merchant'], 'amount' => $d['amount'], 'card_id' => $d['card_id'],
                'category_id' => $d['category_id'], 'occurred_at' => $d['occurred_at'], 'source' => TransactionSource::Chat,
            ]);
            $message->update(['draft_json' => $d + ['saved' => $transaction->id]]);

            return $transaction->id;
        });

        return response()->json(['saved' => true, 'transaction_id' => $id] + ($done ? ['message' => $done] : []));
    }
}
