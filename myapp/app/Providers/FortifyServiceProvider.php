<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Middleware\AdminHostOnly;
use Illuminate\Cache\RateLimiting\Limit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);

        // the login form can send a 6-digit PIN (mode=pin, in the password field) instead of the password
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('email', $request->input('email'))->first();
            $secret = (string) $request->input('password');

            // the admin host signs in admins only; anyone else fails exactly like a wrong password, so it never says who is an admin
            if (! $user || (AdminHostOnly::isAdminHost($request) && ! $user->admin_role)) {
                return null;
            }

            // said only after the password or PIN is right, so it does not reveal which emails have accounts
            $allow = function (User $u) {
                if ($u->suspended_at) {
                    throw ValidationException::withMessages(['email' => __('This account is suspended. Contact support if you think this is a mistake.')]);
                }

                return $u;
            };

            if ($request->input('mode') !== 'pin') {
                return Hash::check($secret, $user->password) ? $allow($user) : null;
            }

            // a 6-digit PIN is guessable, so five misses block it for 15 minutes (the password still works)
            $key = "pin-fails:{$user->id}";
            if (! $user->pin || Cache::get($key, 0) >= 5) {
                return null;
            }

            if (! Hash::check($secret, $user->pin)) {
                Cache::put($key, Cache::get($key, 0) + 1, now()->addMinutes(15));

                return null;
            }

            Cache::forget($key);

            return $allow($user);
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        // the admin host has its own sign-in page, not the public one
        Fortify::loginView(fn (Request $request) => Inertia::render(AdminHostOnly::isAdminHost($request) ? 'admin/Login' : 'auth/Login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/Register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/TwoFactorChallenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('passkeys', function (Request $request) {
            return Limit::perMinute(10)->by(
                ($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
    }
}
