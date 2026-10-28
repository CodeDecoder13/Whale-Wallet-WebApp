<?php

namespace App\Services;

use App\Mail\ReleaseNoteMail;
use App\Models\ReleaseNote;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails a published release note to everyone who should get it, in batches that can be resumed.
 * Each user has one row in release_note_user, so nobody is emailed twice, and a failed address is not retried forever.
 * It runs right after the publish response and again every minute from the scheduler until the note is done.
 */
class ReleaseNoteSender
{
    /** Verified, not suspended, and not unsubscribed. */
    public static function recipients()
    {
        return User::whereNotNull('email_verified_at')->whereNull('suspended_at')->where('release_emails', true);
    }

    /** @return int how many emails were sent in this call */
    public function sendBatch(ReleaseNote $note, int $limit = PHP_INT_MAX): int
    {
        if ($note->status !== 'published' || ! $note->send_email) {
            return 0;
        }

        // the web process and the scheduler may both try at once: only one holds the lock
        $lock = Cache::lock("release-send:{$note->id}", 3600);
        if (! $lock->get()) {
            return 0;
        }

        try {
            set_time_limit(0);
            $sent = 0;

            $todo = fn () => self::recipients()->whereNotExists(
                fn ($q) => $q->select(DB::raw(1))->from('release_note_user')
                    ->whereColumn('release_note_user.user_id', 'users.id')->where('release_note_id', $note->id)
                    ->where(fn ($q) => $q->whereNotNull('emailed_at')->orWhereNotNull('failed_at'))
            )->orderBy('id');

            foreach ($todo()->limit($limit === PHP_INT_MAX ? 100_000 : $limit)->get() as $user) {
                $mark = ['release_note_id' => $note->id, 'user_id' => $user->id];

                try {
                    Mail::to($user)->send(new ReleaseNoteMail($note, $user));
                    DB::table('release_note_user')->updateOrInsert($mark, ['emailed_at' => now()]);
                    $sent++;
                } catch (Throwable $e) {
                    report($e);
                    DB::table('release_note_user')->updateOrInsert($mark, ['failed_at' => now()]);
                }
            }

            $note->increment('emailed_count', $sent);
            $todo()->exists() || $note->update(['email_status' => 'done']);

            return $sent;
        } finally {
            $lock->release();
        }
    }
}
