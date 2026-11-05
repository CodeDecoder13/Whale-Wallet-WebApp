<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiLoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Actions\Fortify\ResetUserPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

class AuthController extends Controller
{
    /** The token's name is the device name; logging in again from the same device replaces its token. */
    public function login(ApiLoginRequest $request): JsonResponse
    {
        // on top of the per-IP route throttle: 5 misses per email a minute, so one account cannot be guessed from many IPs
        $key = 'api-login:'.Str::lower($request->email);

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $s = RateLimiter::availableIn($key);

            throw ValidationException::withMessages(['email' => __('auth.throttle', ['seconds' => $s, 'minutes' => ceil($s / 60)])]);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        abort_if($user->suspended_at, 403, 'This account is suspended. Contact support if you think this is a mistake.');

        // 2FA: the app gets a 422 on "code", then sends the same request again with code (or recovery_code)
        if ($user->two_factor_confirmed_at && ! $this->secondFactorPasses($user, $request)) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages(['code' => $request->filled('code') || $request->filled('recovery_code') ? __('That code is not right.') : __('Two-factor code required.')]);
        }

        RateLimiter::clear($key);

        $user->tokens()->where('name', $request->device_name)->delete();

        return response()->json([
            'token' => $user->createToken($request->device_name)->plainTextToken,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ]);
    }

    private function secondFactorPasses(User $user, ApiLoginRequest $request): bool
    {
        if ($request->filled('recovery_code')) {
            $code = collect($user->recoveryCodes())->first(fn ($c) => hash_equals($c, $request->recovery_code));

            return $code && tap(true, fn () => $user->replaceRecoveryCode($code));
        }

        return $request->filled('code') && app(TwoFactorAuthenticationProvider::class)->verify(decrypt($user->two_factor_secret), $request->code);
    }

    /** Always answers 200, so the endpoint cannot be used to find out which emails are registered. */
    public function forgotPassword(Request $request): JsonResponse
    {
        Password::sendResetLink($request->validate(['email' => ['required', 'email']]));

        return response()->json(['message' => __('If that email has an account, a reset link is on its way.')]);
    }

    /** The token comes from the emailed link. Resetting signs the account out of every device. */
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string'], 'email' => ['required', 'email'], 'password' => ['required', 'string', 'confirmed']]);

        $status = Password::reset($data, function (User $user, string $password) {
            app(ResetUserPassword::class)->reset($user, ['password' => $password, 'password_confirmation' => $password]);
            $user->tokens()->delete();
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return response()->json(['message' => __('Password changed. Sign in again.')]);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $request->user()->hasVerifiedEmail() || $request->user()->sendEmailVerificationNotification();

        return response()->json(null, 202);
    }

    /** The signed-in user's tokens, one per device. */
    public function tokens(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->tokens()->latest('id')->get(['id', 'name', 'created_at', 'last_used_at'])]);
    }

    /** Only the user's own tokens resolve, so another user's id is a 404. */
    public function revokeToken(Request $request, int $token): JsonResponse
    {
        $request->user()->tokens()->findOrFail($token)->delete();

        return response()->json(null, 204);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }

    /** Account deletion for the app (stores require it); needs the password like the web form. */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $request->user()->delete();

        return response()->json(null, 204);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
