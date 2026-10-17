<?php

use App\Actions\GenerateRecommendations;
use App\Models\RecurringItem;
use App\Models\User;
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
