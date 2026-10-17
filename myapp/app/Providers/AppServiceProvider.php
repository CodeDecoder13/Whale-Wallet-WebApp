<?php

namespace App\Providers;

use App\Contracts\AiProvider;
use App\Services\Ai\FakeAiProvider;
use App\Services\Ai\GeminiProvider;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
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

        // every sign-in gets the welcome-back window on its next dashboard view
        Event::listen(Login::class, fn () => session()->put('welcome', true));

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
