<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\SupportNotice;
use App\Models\AdminAudit;
use App\Models\SupportMessage;
use App\Models\SupportThread;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The admin inbox: every user's tickets, on the left the list, on the right the open one. Owners and support admins both use it.
 * Closed tickets are never deleted: a user closing one only ends it for them, and the conversation stays here.
 */
class SupportController extends Controller
{
    private function thread(int $id): SupportThread
    {
        return SupportThread::withoutGlobalScopes()->with('user:id,name,email')->findOrFail($id);
    }

    public function index(Request $request): Response
    {
        $filter = in_array($request->query('filter'), ['open', 'unread', 'closed'], true) ? $request->query('filter') : 'open';

        $threads = SupportThread::withoutGlobalScopes()->with('user:id,name,email')
            ->addSelect(['last_body' => SupportMessage::withoutGlobalScopes()->select('body')->whereColumn('support_thread_id', 'support_threads.id')->latest('id')->limit(1)])
            ->when($filter === 'open', fn ($q) => $q->where('status', 'open'))
            ->when($filter === 'closed', fn ($q) => $q->where('status', 'closed'))
            ->when($filter === 'unread', fn ($q) => $q->where('admin_unread', '>', 0))
            ->orderByDesc('last_message_at')->limit(100)->get()
            // ponytail: the 100 newest tickets, no paging; add paging when the inbox grows past that
            ->map(fn ($t) => [
                'id' => $t->id, 'status' => $t->status, 'unread' => $t->admin_unread, 'at' => $t->last_message_at?->toIso8601String(), 'subject' => $t->subject,
                'preview' => str($t->last_body ?: 'Sent a file')->limit(70)->toString(), 'user' => $t->user?->only('id', 'name', 'email'),
            ]);

        $open = null;
        $messages = [];

        if ($id = (int) $request->query('thread')) {
            $t = $this->thread($id);
            AdminAudit::record($request->user(), 'viewed_support', $t->user);
            // opening a ticket marks what the user wrote as read, which is the user's "Seen"
            SupportMessage::withoutGlobalScopes()->where('support_thread_id', $t->id)->where('from_admin', false)->whereNull('read_at')->update(['read_at' => now()]);
            $t->update(['admin_unread' => 0]);

            $open = ['id' => $t->id, 'status' => $t->status, 'subject' => $t->subject, 'closed_by' => $t->closed_by, 'user' => $t->user?->only('id', 'name', 'email')];
            $messages = SupportMessage::withoutGlobalScopes()->where('support_thread_id', $t->id)->latest('id')->limit(200)->get()->reverse()->values()->map->toClient();
        }

        return Inertia::render('admin/Support', ['threads' => $threads, 'open' => $open, 'messages' => $messages, 'filter' => $filter]);
    }

    public function reply(Request $request, int $thread): RedirectResponse
    {
        $request->merge(['body' => trim((string) $request->input('body'))]);
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:1000', 'required_without:file'],
            'file' => ['nullable', 'required_without:body', ...config('support.file_rules')],
        ]);
        $t = $this->thread($thread);
        $body = $data['body'] ?? '';

        $message = new SupportMessage(['sender_id' => $request->user()->id, 'from_admin' => true, 'body' => $body, 'created_at' => now(), ...SupportMessage::fileColumns($request->file('file'), $t->id)]);
        $message->user_id = $t->user_id; // the ticket's owner, not the admin
        $message->support_thread_id = $t->id;
        $message->save();

        $t->increment('user_unread');
        $t->forceFill(['status' => 'open', 'closed_at' => null, 'closed_by' => null, 'last_message_at' => now()])->save(); // a reply reopens a closed ticket
        AdminAudit::record($request->user(), 'support_reply', $t->user, $message->attachment_path ? ['file' => $message->attachment_name] : []);

        // email the user only if they are not in the app right now, and at most once per 10 minutes
        $here = DB::table('sessions')->where('user_id', $t->user_id)->where('last_activity', '>=', now()->subMinutes(10)->timestamp)->exists();
        if ($t->user && ! $here && Cache::add("support-mail-user:{$t->user_id}", true, now()->addMinutes(10))) {
            $notice = new SupportNotice($t->user->name, 'Whale Wallet support replied', $body !== '' ? $body : 'Sent you a file.', route('support.index', ['ticket' => $t->id]), 'Open the chat');
            dispatch(fn () => Mail::to($t->user)->send($notice))->afterResponse();
        }

        return back();
    }

    public function status(Request $request, int $thread): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:open,closed']]);
        $t = $this->thread($thread);
        $t->update($data['status'] === 'closed'
            ? ['status' => 'closed', 'closed_at' => now(), 'closed_by' => 'admin']
            : ['status' => 'open', 'closed_at' => null, 'closed_by' => null]);
        AdminAudit::record($request->user(), "support_{$data['status']}", $t->user);

        return back();
    }

    /** An attachment from either side, only through this gated, logged route. */
    public function file(Request $request, int $message)
    {
        $m = SupportMessage::withoutGlobalScopes()->findOrFail($message);
        AdminAudit::record($request->user(), 'opened_support_file', $this->thread($m->support_thread_id)->user, ['file' => $m->attachment_name]);

        return $m->download();
    }
}
