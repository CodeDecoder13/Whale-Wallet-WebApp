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
use Inertia\Inertia;
use Inertia\Response;

/** The user's side of the support chat: their one conversation with the team. Both models are scoped to the signed-in user. */
class SupportController extends Controller
{
    public function index(): Response
    {
        $thread = SupportThread::first();

        if ($thread) {
            // opening the chat is what marks the admin's replies as read
            SupportMessage::where('support_thread_id', $thread->id)->where('from_admin', true)->whereNull('read_at')->update(['read_at' => now()]);
            $thread->update(['user_unread' => 0]);
        }

        return Inertia::render('support/Index', [
            'status' => $thread?->status ?? 'open',
            'messages' => $thread
                ? SupportMessage::where('support_thread_id', $thread->id)->latest('id')->limit(100)->get()->reverse()->values()->map->toClient()
                : [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['body' => trim((string) $request->input('body'))]);
        $data = $request->validate(['body' => ['required', 'string', 'max:1000']]);
        $user = $request->user();

        $thread = SupportThread::firstOrCreate([], ['status' => 'open']);
        $thread->messages()->create(['sender_id' => $user->id, 'from_admin' => false, 'body' => $data['body'], 'created_at' => now()]);
        $thread->increment('admin_unread');
        $thread->forceFill(['status' => 'open', 'last_message_at' => now()])->save(); // a closed chat reopens when the user writes
        ActivityLog::record('support_message', $user->id);

        // one email to the admins per chat per 10 minutes, so a burst of messages is one email
        if (Cache::add("support-mail:{$thread->id}", true, now()->addMinutes(10))) {
            $admins = User::whereNotNull('admin_role')->whereNull('suspended_at')->get();
            $notice = new SupportNotice($user->name, "New support message from {$user->name}", $data['body'], route('admin.support', ['thread' => $thread->id]), 'Open the chat');
            dispatch(fn () => $admins->each(fn ($admin) => Mail::to($admin)->send($notice)))->afterResponse();
        }

        return back();
    }
}
