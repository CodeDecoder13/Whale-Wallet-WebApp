<?php

namespace Database\Seeders;

use App\Enums\CardType;
use App\Enums\RecurringKind;
use App\Models\Category;
use App\Models\RecurringItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Demo user from the founder's example. All amounts in centavos. */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@example.com',
            'pay_dates' => [15, 30],
        ]);

        $user->incomeSources()->create(['name' => 'Salary', 'amount' => 3_500_000]);
        $user->cards()->create(['name' => 'GCash', 'type' => CardType::Ewallet, 'balance' => 500_000]);

        Category::seedFor($user);

        $items = [
            ['Loan', 900_000, RecurringKind::Debt, 27],
            ['Rent', 800_000, RecurringKind::Bill, 1],
            ['Netflix', 54_900, RecurringKind::Subscription, 5],
            ['Spotify', 14_900, RecurringKind::Subscription, 8],
            ['iCloud', 7_900, RecurringKind::Subscription, 12],
            ['Disney+', 36_900, RecurringKind::Subscription, 15],
            ['YouTube Premium', 18_900, RecurringKind::Subscription, 18],
            ['Gym', 15_900, RecurringKind::Subscription, 20],
            ['ChatGPT', 66_200, RecurringKind::Subscription, 22],
        ]; // subscriptions sum to 215_600 (2,156 pesos)

        foreach ($items as [$name, $amount, $kind, $day]) {
            $user->recurringItems()->create([
                'name' => $name,
                'amount' => $amount,
                'kind' => $kind,
                'next_due_on' => RecurringItem::nextDue($day),
            ]);
        }
    }
}
