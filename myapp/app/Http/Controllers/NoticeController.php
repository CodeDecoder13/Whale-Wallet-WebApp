<?php

namespace App\Http\Controllers;

use App\Models\ReleaseNote;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** The user's side of release notes: dismissing the popup, and the one-click unsubscribe in the email. */
class NoticeController extends Controller
{
    /** "Got it": this note and every older popup note count as seen, so a missed one never stacks up behind it. */
    public function seen(Request $request, int $note)
    {
        $ids = ReleaseNote::where('status', 'published')->where('show_popup', true)->where('id', '<=', $note)->pluck('id');

        foreach ($ids as $id) {
            DB::table('release_note_user')->updateOrInsert(['release_note_id' => $id, 'user_id' => $request->user()->id], ['seen_at' => now()]);
        }

        return back();
    }

    /** The link in the email is signed, so it works without signing in and cannot be made for someone else. */
    public function unsubscribe(User $user): Response
    {
        $user->forceFill(['release_emails' => false])->save();

        return Inertia::render('legal/Unsubscribed');
    }
}
