<?php

use App\Actions\GenerateRecommendations;
use App\Models\RecurringItem;
use App\Models\User;
use App\Services\PushAlerts;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('recurring:advance', function () {
    // no signed-in user here, so the BelongsToUser scope does not apply
    $items = RecurringItem::whereDate('next_due_on', '<', today())->get();
    $items->each->advance();
    $this->info("Advanced {$items->count()} recurring items.");
})->purpose('Roll overdue recurring items forward to their next due date');

Schedule::command('recurring:advance')->daily();

Artisan::command('recommendations:generate {email}', function (GenerateRecommendations $generate) {
    $user = User::where('email', $this->argument('email'))->first();

    if (! $user) {
        $this->error('No user with that email.');

        return 1;
    }

    try {
        $tips = $generate->handle($user);
    } catch (Throwable $e) {
        // keep the key out of the message in case an HTTP error echoes the request URL
        $this->error(str_replace((string) config('ai.providers.gemini.key') ?: "\0", '***', $e->getMessage()));

        return 1;
    }

    $tips->each(fn ($tip) => $this->line("- {$tip->text}"));
    $this->info("Saved {$tips->count()} tips using ".config('services.gemini.model').'.');
})->purpose('Generate AI tips for a user with the configured provider (real Gemini call; needs GEMINI_API_KEY)');

Artisan::command('push:bills', function (PushAlerts $push) {
    $this->info("Sent {$push->bills()} bill alerts.");
})->purpose('Push a reminder for bills due in the next 2 days (Expo)');

Artisan::command('push:budgets', function (PushAlerts $push) {
    $this->info("Sent {$push->budgets()} budget alerts.");
})->purpose('Push a warning for cards at 80% of, or over, their monthly limit (Expo)');

Schedule::command('push:bills')->dailyAt('08:00')->withoutOverlapping();
Schedule::command('push:budgets')->hourly()->withoutOverlapping();

Artisan::command('push:payday', fn (PushAlerts $push, \App\Services\Forecast\ForecastLoader $loader) => $this->info("Sent {$push->payday($loader)} payday alerts."))
    ->purpose('Push "payday near" 1 and 2 days before payday (Expo)');
Artisan::command('push:daily', fn (PushAlerts $push, \App\Services\Forecast\ForecastLoader $loader) => $this->info("Sent {$push->daily($loader)} daily alerts."))
    ->purpose('Push the safe-to-spend amount once a day (Expo)');

Schedule::command('push:payday')->dailyAt('08:00')->withoutOverlapping();
Schedule::command('push:daily')->dailyAt('07:30')->withoutOverlapping();
