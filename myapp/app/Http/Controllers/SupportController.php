<?php

namespace App\Http\Controllers;

use App\Mail\SupportNotice;
use App\Models\ActivityLog;
use App\Models\SupportMessage;
use App\Models\SupportThread;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The user's side of support: tickets with the team. A user can close a ticket and start another, and everything
 * they wrote stays on our side for safekeeping. Both models are scoped to the signed-in user.
 */
class SupportController extends Controller
{
    public function index(Request $request): Response
    {
        $tickets = SupportThread::latest('id')->limit(30)->get();

        // the ticket on screen: the one asked for, else the newest open one; ?new=1 shows the empty page with the question templates
        $current = $request->boolean('new') ? null
            : ($request->query('ticket') ? SupportThread::findOrFail((int) $request->query('ticket')) : $tickets->firstWhere('status', 'open'));

        if ($current) {
            // opening a ticket is what marks the team's replies as read
            SupportMessage::where('support_thread_id', $current->id)->where('from_admin', true)->whereNull('read_at')->update(['read_at' => now()]);
            $current->update(['user_unread' => 0]);
        }

        return Inertia::render('support/Index', [
            'ticket' => $current?->only('id', 'status', 'subject', 'closed_by'),
            'messages' => $current
                ? SupportMessage::where('support_thread_id', $current->id)->latest('id')->limit(100)->get()->reverse()->values()->map->toClient()
                : [],
            'history' => $tickets->map(fn ($t) => ['id' => $t->id, 'subject' => $t->subject, 'status' => $t->status, 'at' => ($t->last_message_at ?? $t->created_at)->toIso8601String()]),
            'templates' => collect(config('support.templates'))->map(fn ($t, $key) => ['key' => $key, ...$t])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['body' => trim((string) $request->input('body'))]);
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:1000', 'required_without:file'],
            'file' => ['nullable', 'required_without:body', ...config('support.file_rules')],
            'ticket' => ['nullable', 'integer'],
            'template' => ['nullable', 'in:'.implode(',', array_keys(config('support.templates')))],
        ]);
        $user = $request->user();
        $body = $data['body'] ?? '';

        // write into the open ticket (the one on screen, else the newest open one); with none open this message starts a new ticket, and a closed ticket stays closed
        $thread = SupportThread::where('status', 'open')->when($data['ticket'] ?? null, fn ($q, $id) => $q->whereKey($id))->latest('id')->first();
        $isNew = ! $thread;
        $thread ??= SupportThread::create([
            'status' => 'open',
            'subject' => isset($data['template']) ? config("support.templates.{$data['template']}.label") : Str::limit($body !== '' ? $body : ($request->file('file')?->getClientOriginalName() ?? 'File'), 80),
        ]);

        $thread->messages()->create([
            'sender_id' => $user->id, 'from_admin' => false, 'body' => $body, 'created_at' => now(),
            ...SupportMessage::fileColumns($request->file('file'), $thread->id),
        ]);
        $thread->increment('admin_unread');
        $thread->forceFill(['last_message_at' => now()])->save();
        ActivityLog::record('support_message', $user->id);

        // a new ticket is always an email; later messages are one email per ticket per 10 minutes
        $key = "support-mail:{$thread->id}";
        $send = $isNew || Cache::add($key, true, now()->addMinutes(10));
        $isNew && Cache::put($key, true, now()->addMinutes(10)); // the next message in this ticket waits 10 minutes for its own email

        if ($send) {
            $admins = User::whereNotNull('admin_role')->whereNull('suspended_at')->get();
            $notice = new SupportNotice($user->name, ($isNew ? 'New support ticket from ' : 'New support message from ').$user->name, $body !== '' ? $body : 'Sent a file.', route('admin.support', ['thread' => $thread->id]), 'Open the chat');
            dispatch(fn () => $admins->each(fn ($admin) => Mail::to($admin)->send($notice)))->afterResponse();
        }

        return to_route('support.index', ['ticket' => $thread->id]);
    }

    /** "End ticket": it closes for both sides; the team keeps the whole conversation. */
    public function close(int $thread): RedirectResponse
    {
        SupportThread::findOrFail($thread)->update(['status' => 'closed', 'closed_at' => now(), 'closed_by' => 'user']);

        return to_route('support.index', ['new' => 1]);
    }

    public function file(int $message)
    {
        return SupportMessage::findOrFail($message)->download();
    }
}
