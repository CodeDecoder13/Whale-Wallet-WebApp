<?php

namespace App\Http\Controllers;

use App\Actions\CreateTransaction;
use App\Actions\HandleChatMessage;
use App\Actions\StoreReceipt;
use App\Enums\TransactionSource;
use App\Models\ChatMessage;
use App\Models\ChatThread;
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
        $thread = ChatThread::latest('id')->first();

        return Inertia::render('chat/Index', [
            'consented' => (bool) $request->user()->ai_consent_at,
            'messages' => $thread?->messages()->latest('id')->limit(50)->get(['id', 'role', 'content', 'draft_json', 'receipt_id'])->reverse()->values() ?? [],
        ]);
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
            $receipt = $store->handle($user, $request->file('image'));
            $reply = 'Got your receipt. I am reading it now; confirm it on the Receipts page.';
            $thread->messages()->create(['role' => 'user', 'content' => $data['text'] ?? 'Sent a receipt', 'receipt_id' => $receipt->id]);
            $message = $thread->messages()->create(['role' => 'assistant', 'content' => $reply, 'receipt_id' => $receipt->id]);

            return response()->json(['id' => $message->id, 'reply' => $reply, 'receipt_id' => $receipt->id, 'thread_id' => $thread->id]);
        }

        try {
            $out = $chat->handle($user, $thread, $data['text']);
        } catch (Throwable $e) {
            report($e); // the client only gets a fixed sentence, never the provider's text

            return response()->json(['message' => AiErrors::message($e), 'manual_url' => route('transactions.index')], AiErrors::isQuota($e) ? 429 : 503);
        }

        return response()->json($out + ['thread_id' => $thread->id]);
    }

    /** The only place a chat draft becomes a transaction. */
    public function confirm(ChatMessage $message, CreateTransaction $create): JsonResponse
    {
        $id = DB::transaction(function () use ($message, $create) {
            $message = ChatMessage::lockForUpdate()->findOrFail($message->id);
            $d = $message->draft_json;
            abort_unless($d && ! $d['missing'] && empty($d['saved']), 422, 'There is no complete draft to save here.');

            $transaction = $create->handle([
                'merchant' => $d['merchant'], 'amount' => $d['amount'], 'card_id' => $d['card_id'],
                'category_id' => $d['category_id'], 'occurred_at' => $d['occurred_at'], 'source' => TransactionSource::Chat,
            ]);
            $message->update(['draft_json' => $d + ['saved' => $transaction->id]]);

            return $transaction->id;
        });

        return response()->json(['saved' => true, 'transaction_id' => $id]);
    }
}
