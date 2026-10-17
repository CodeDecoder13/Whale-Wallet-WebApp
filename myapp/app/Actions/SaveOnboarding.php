<?php

namespace App\Actions;

use App\Enums\RecurringKind;
use App\Models\Category;
use App\Models\RecurringItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaveOnboarding
{
    /** $data is validated OnboardingRequest input (amounts in centavos). */
    public function handle(User $user, array $data): void
    {
        DB::transaction(function () use ($user, $data) {
            // forceFill: pay_dates and pay_cycle are not mass-assignable on User, update() would drop them
            $user->forceFill([
                'pay_dates' => array_map('intval', $data['pay_dates']),
                'pay_cycle' => count($data['pay_dates']) > 1 ? 'semi_monthly' : 'monthly',
            ])->save();
            $user->incomeSources()->create(['name' => 'Income', 'amount' => $data['income']]);

            foreach ($data['bills'] ?? [] as $bill) {
                $user->recurringItems()->create([
                    'name' => $bill['name'],
                    'amount' => $bill['amount'],
                    'kind' => RecurringKind::Bill,
                    'next_due_on' => RecurringItem::nextDue($bill['day']),
                ]);
            }

            $user->cards()->create($data['card']);

            if (! empty($data['pod']['name'])) {
                $user->pods()->create($data['pod']);
            }

            Category::seedFor($user);
        });
    }
}
