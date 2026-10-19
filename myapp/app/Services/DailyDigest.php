<?php

namespace App\Services;

use App\Actions\GenerateRecommendations;
use App\Mail\DailyDigest as DigestMail;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Once a day, at each user's chosen time (Philippine time, 09:00 by default), refresh their Whale tips so the
 * dashboard shows today's, and email them to the users who opted in. Run every few minutes by the scheduler.
 */
class DailyDigest
{
    public const TZ = 'Asia/Manila';

    public function __construct(private GenerateRecommendations $tips) {}

    /** @return array{refreshed: int, emailed: int} */
    public function run(): array
    {
        $now = now(self::TZ);
        $out = ['refreshed' => 0, 'emailed' => 0];

        $due = User::whereNotNull('ai_consent_at')
            ->where(fn ($q) => $q->whereNull('digest_sent_on')->orWhere('digest_sent_on', '<', $now->toDateString()))
            ->where('digest_time', '<=', $now->format('H:i')) // "09:00" strings sort like times
            ->cursor();

        foreach ($due as $user) {
            // a failing AI call is retried, but not every five minutes
            if (Cache::has("digest:failed:{$user->id}")) {
                continue;
            }

            // signed in as the user so the per-user scopes apply, as in a web request
            Auth::setUser($user);

            try {
                $tips = $this->tips->handle($user);
                $out['refreshed']++;

                if ($user->digest_enabled && $user->hasVerifiedEmail()) {
                    Mail::to($user)->send(new DigestMail($user, $tips));
                    $out['emailed']++;
                }

                $user->forceFill(['digest_sent_on' => $now->toDateString()])->save();
            } catch (Throwable $e) {
                report($e);
                Cache::put("digest:failed:{$user->id}", true, now()->addHour());
            } finally {
                Auth::forgetUser();
            }
        }

        return $out;
    }
}
