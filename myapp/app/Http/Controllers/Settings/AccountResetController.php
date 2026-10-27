<?php

namespace App\Http\Controllers\Settings;

use App\Actions\ImportUserData;
use App\Http\Controllers\Controller;
use App\Mail\VerifyCode;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Receipt;
use App\Models\Recommendation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/** "Reset to a new account": wipes the user's money data after the 6-digit PIN and a code emailed to them. */
class AccountResetController extends Controller
{
    private const MAX_TRIES = 5;

    public function code(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->pin, 422, 'Set a login PIN first (Security).');

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put("reset-otp:{$user->id}", Hash::make($code), now()->addMinutes(10));
        Cache::forget("reset-tries:{$user->id}");
        dispatch(fn () => Mail::to($user)->send(new VerifyCode($user->name, $code, 'reset')))->afterResponse();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('We emailed you a code.')]);

        return back();
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate(['pin' => ['required', 'digits:6'], 'code' => ['required', 'digits:6']]);
        $user = $request->user();
        $hash = Cache::get("reset-otp:{$user->id}");
        $tries = "reset-tries:{$user->id}";

        if (! $user->pin || ! $hash || Cache::get($tries, 0) >= self::MAX_TRIES) {
            throw ValidationException::withMessages(['code' => __('That code has expired. Send a new one.')]);
        }

        // both must be right; one shared counter so neither can be guessed
        if (! Hash::check($data['pin'], $user->pin) || ! Hash::check($data['code'], $hash)) {
            Cache::put($tries, Cache::get($tries, 0) + 1, now()->addMinutes(10));

            throw ValidationException::withMessages(['code' => __('The PIN or the code is not right.')]);
        }

        Cache::forget("reset-otp:{$user->id}");
        DB::transaction(function () use ($user, $request) {
            ImportUserData::wipe();
            Receipt::get()->each(function ($r) {
                Storage::disk()->delete($r->path);
                $r->delete();
            });
            Recommendation::query()->delete();
            app(DataController::class)->clearChat($request);
            Category::seedFor($user);
        });

        ActivityLog::record('account_reset', $user->id);

        // no cards left, so the dashboard sends them through onboarding again
        return to_route('dashboard');
    }
}
