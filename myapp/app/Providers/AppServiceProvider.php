<?php

namespace App\Providers;

use App\Contracts\AiProvider;
use App\Services\Ai\FakeAiProvider;
use App\Services\Ai\GeminiProvider;
use Carbon\CarbonImmutable;
use App\Models\ActivityLog;
use App\Services\LoginAlert;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Str;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // AI_FAKE=true runs the whole app on the offline fake (demos, browser checks); tests always bind the fake
        $this->app->bind(AiProvider::class, config('services.fake_ai') ? FakeAiProvider::class : GeminiProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // the reset-password email uses the Whale Wallet design instead of Laravel's default
        ResetPassword::toMailUsing(fn ($user, string $token) => (new MailMessage)
            ->subject('Reset your Whale Wallet password')
            ->view(['mail.reset-password', 'mail.reset-password-text'], [
                'name' => $user->name,
                'url' => url(route('password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset()], false)),
                'minutes' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
            ]));

        // every sign-in gets the welcome-back window on its next dashboard view
        Event::listen(Login::class, fn () => session()->put('welcome', true));

        // the admin activity feed: one line per sign-in, sign-up, verification, reset and failed login
        Event::listen(Login::class, fn (Login $e) => ActivityLog::record('login', $e->user->getAuthIdentifier(), ['device' => Str::limit((string) request()?->userAgent(), 120)]));
        // the owners get an email for it, unless they turned login alerts off (a failure here never reaches the user)
        Event::listen(Login::class, function (Login $e) {
            try {
                app(LoginAlert::class)->handle($e->user, request());
            } catch (\Throwable $ex) {
                report($ex);
            }
        });
        Event::listen(Registered::class, fn (Registered $e) => ActivityLog::record('registered', $e->user->getAuthIdentifier()));
        Event::listen(Verified::class, fn (Verified $e) => ActivityLog::record('verified', $e->user->getAuthIdentifier()));
        Event::listen(PasswordReset::class, fn (PasswordReset $e) => ActivityLog::record('password_reset', $e->user->getAuthIdentifier()));
        Event::listen(Failed::class, fn (Failed $e) => ActivityLog::record('login_failed', $e->user?->getAuthIdentifier(), ['email' => $e->credentials['email'] ?? null]));

        // ponytail: failed jobs go to the log at critical level; point LOG_STACK at slack/sentry for real alerts
        Queue::failing(fn (JobFailed $e) => Log::critical('Queue job failed', ['job' => $e->job->resolveName(), 'error' => $e->exception->getMessage()]));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
