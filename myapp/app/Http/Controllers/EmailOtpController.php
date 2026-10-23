<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class EmailOtpController extends Controller
{
    private const MAX_TRIES = 5;

    /** Checks the 6-digit code from the email; on success the account is verified and the user lands on the dashboard. */
    public function __invoke(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'digits:6']]);
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return to_route('dashboard');
        }

        $key = "otp:{$user->id}";
        $tries = "otp-tries:{$user->id}";
        $hash = Cache::get($key);

        if (! $hash || Cache::get($tries, 0) >= self::MAX_TRIES) {
            throw ValidationException::withMessages(['code' => __('That code has expired. Send a new one.')]);
        }

        if (! Hash::check($request->input('code'), $hash)) {
            Cache::put($tries, Cache::get($tries, 0) + 1, now()->addMinutes(10));

            throw ValidationException::withMessages(['code' => __('That code is not right.')]);
        }

        Cache::forget($key);
        Cache::forget($tries);
        $user->markEmailAsVerified();

        return to_route('dashboard');
    }
}
