<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAudit;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every Whale Chat (AI) conversation, read-only. Threads a user deleted from their history stay deleted:
 * they are not listed here either, so "delete" means the same to the user and to the admin.
 */
class ChatController extends Controller
{
    public function index(Request $request): Response
    {
        $term = '%'.mb_strtolower(trim((string) $request->query('q'))).'%';

        // withoutGlobalScopes() also drops soft-delete hiding, so deleted threads are excluded by hand
        $threads = ChatThread::withoutGlobalScopes()->whereNull('deleted_at')->with('user:id,name,email')
            ->withCount(['messages' => fn ($q) => $q->withoutGlobalScopes()])
            ->addSelect(['last_at' => ChatMessage::withoutGlobalScopes()->selectRaw('max(created_at)')->whereColumn('chat_thread_id', 'chat_threads.id')])
            ->when($request->query('user'), fn ($q, $id) => $q->where('user_id', (int) $id))
            ->when($request->query('q'), fn ($q) => $q->whereIn('id', ChatMessage::withoutGlobalScopes()->whereRaw('lower(content) like ?', [$term])->select('chat_thread_id')))
            ->latest('updated_at')->paginate(25)->withQueryString()
            ->through(fn (ChatThread $t) => [
                'id' => $t->id, 'title' => $t->title, 'messages' => $t->messages_count, 'last_at' => $t->last_at, 'user' => $t->user?->only('id', 'name', 'email'),
            ]);

        return Inertia::render('admin/Chats', ['threads' => $threads, 'filters' => $request->only('q', 'user')]);
    }

    public function show(Request $request, int $thread): Response
    {
        $t = ChatThread::withoutGlobalScopes()->whereNull('deleted_at')->with('user:id,name,email')->findOrFail($thread);
        AdminAudit::record($request->user(), 'viewed_chat', $t->user, ['thread_id' => $t->id]);

        return Inertia::render('admin/ChatShow', [
            'thread' => ['id' => $t->id, 'title' => $t->title, 'user' => $t->user?->only('id', 'name', 'email')],
            'messages' => ChatMessage::withoutGlobalScopes()->where('chat_thread_id', $t->id)->orderBy('id')->limit(500)
                ->get(['id', 'role', 'content', 'draft_json', 'created_at'])
                ->map(fn ($m) => [
                    'id' => $m->id, 'role' => $m->role, 'content' => $m->content, 'at' => $m->created_at->toIso8601String(),
                    'draft' => $m->draft_json ? ['merchant' => $m->draft_json['merchant'] ?? null, 'amount' => $m->draft_json['amount'] ?? null] : null,
                ]),
        ]);
    }
}
