<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
        $data = ['exported_at' => now()->toIso8601String(), 'user' => $user->only('name', 'email')];

        foreach (['incomeSources', 'cards', 'pods', 'categories', 'recurringItems', 'transactions', 'recommendations'] as $rel) {
            $data[Str::snake($rel)] = $user->$rel()->get();
        }
        $data['receipts'] = $user->receipts()->get()->makeHidden('path'); // the image's storage path is internal
        $data['chat_threads'] = $user->chatThreads()->with('messages')->get();

        return response()->json($data)->header('Content-Disposition', 'attachment; filename="whale-wallet-export.json"');
    }

    public function clearChat(Request $request)
    {
        ChatMessage::query()->delete();
        ChatThread::query()->delete();

        return $request->expectsJson() ? response()->noContent() : back();
    }
}
