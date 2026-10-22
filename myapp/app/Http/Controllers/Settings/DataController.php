<?php

namespace App\Http\Controllers\Settings;

use App\Actions\ImportUserData;
use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Everything the user owns, as one JSON download, and the chat-history wipe. */
class DataController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('settings/Data');
    }

    public function export(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = ['format' => 'whale-wallet', 'version' => 1, 'exported_at' => now()->toIso8601String(), 'user' => $user->only('name', 'email', 'pay_dates', 'digest_enabled', 'digest_time', 'keep_receipts')];

        foreach (['incomeSources', 'cards', 'pods', 'categories', 'recurringItems', 'transactions', 'recommendations'] as $rel) {
            $data[Str::snake($rel)] = $user->$rel()->get();
        }
        $data['receipts'] = $user->receipts()->get()->makeHidden('path'); // the image's storage path is internal
        $data['chat_threads'] = $user->chatThreads()->with('messages')->get();

        return response()->json($data)->header('Content-Disposition', 'attachment; filename="whale-wallet-export.json"');
    }

    /** Loads an export from this app (or another account) into the signed-in account; ImportUserData says how rows are matched. */
    public function import(Request $request, ImportUserData $import): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:20480'], 'replace' => ['nullable', 'boolean']]);
        $data = json_decode($request->file('file')->get(), true);

        if (! is_array($data)) {
            throw ValidationException::withMessages(['file' => 'That file is not valid JSON. Use a file downloaded from Whale Wallet.']);
        }

        $n = $import->handle($request->user(), $data, $request->boolean('replace'));
        $added = collect($n)->except('skipped')->filter()->map(fn ($count, $what) => $count.' '.str_replace('_', ' ', $what))->implode(', ');

        Inertia::flash('toast', ['type' => 'success', 'message' => ($added ?: 'Nothing new to import').($n['skipped'] ? ' ('.$n['skipped'].' already there)' : '').'.']);

        return back();
    }

    public function clearChat(Request $request)
    {
        ChatMessage::query()->delete();
        // the full wipe also removes chats already deleted from history; per model, because the bulk forceDelete macro skips the user scope
        ChatThread::withTrashed()->get()->each->forceDelete();

        return $request->expectsJson() ? response()->noContent() : back();
    }
}
