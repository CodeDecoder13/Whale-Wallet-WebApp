<?php

namespace App\Services;

use App\Mail\LoginAlertMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Tells the owners when a user signs in. It runs after the response, so signing in never waits on mail and
 * never fails because of it, and it needs no queue worker.
 */
class LoginAlert
{
    /** "Chrome on Windows", from the user agent; good enough to recognise a device, not a fingerprint. */
    public static function device(string $ua): string
    {
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Chrome/') || str_contains($ua, 'CriOS') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => 'Browser',
        };

        $os = match (true) {
            str_contains($ua, 'iPhone') => 'iPhone',
            str_contains($ua, 'iPad') => 'iPad',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh') => 'Mac',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'unknown device',
        };

        return "{$browser} on {$os}";
    }

    public function handle(User $user, Request $request): void
    {
        // the owners' own sign-ins are not news
        if ($user->admin_role) {
            return;
        }

        $ua = (string) $request->userAgent();
        $ip = $request->ip();
        $hash = sha1(strtolower($ua));

        // the same browser and address signing in twice within 10 minutes (a page reload, a second tab) is one alert
        if (! Cache::add("login-alert:{$user->id}:{$hash}:{$ip}", true, now()->addMinutes(10))) {
            return;
        }

        $device = self::device($ua);
        $known = DB::table('login_devices')->where('user_id', $user->id)->where('ua_hash', $hash);
        $new = ! $known->exists();

        $new
            ? DB::table('login_devices')->insert(['user_id' => $user->id, 'ua_hash' => $hash, 'label' => $device, 'last_ip' => $ip, 'first_seen_at' => now(), 'last_seen_at' => now()])
            : $known->update(['last_ip' => $ip, 'last_seen_at' => now()]);

        $owners = User::where('admin_role', 'owner')->whereNull('suspended_at')->where('login_alerts', '!=', 'off')->get()
            ->filter(fn (User $o) => $o->login_alerts === 'all' || $new);

        if ($owners->isEmpty()) {
            return;
        }

        $mail = new LoginAlertMail($user, $device, $ip, $new, now(DailyDigest::TZ)->format('D j M Y, g:i A').' (Philippine time)', route('admin.users.show', $user));

        dispatch(function () use ($owners, $mail) {
            // one failing address must not stop the others, and nothing here may surface to the user who signed in
            foreach ($owners as $owner) {
                try {
                    Mail::to($owner)->send($mail);
                } catch (Throwable $e) {
                    report($e);
                }
            }
        })->afterResponse();
    }
}
