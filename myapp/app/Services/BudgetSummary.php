<?php

namespace App\Services;

use App\Models\User;

class BudgetSummary
{
    /**
     * Monthly figures in centavos. Recurring items are normalised by cadence.
     * Income is the salary received this month if any was logged, else the onboarding baseline, plus any side income received this month.
     *
     * @return array{income: int, fixed: int, remaining: int}
     */
    public function for(User $user): array
    {
        $month = fn (string $kind) => $user->incomeSources()->where('month', now()->format('Y-m'))->where('kind', $kind);
        $salary = $month('salary');
        $income = (int) ($salary->exists() ? $salary : $user->incomeSources()->whereNull('month'))->sum('amount')
            + (int) $month('side')->sum('amount'); // side income never replaces the baseline
        $fixed = $user->recurringItems()->active()->get()->sum(fn ($item) => $item->monthlyAmount());

        return ['income' => $income, 'fixed' => $fixed, 'remaining' => $income - $fixed];
    }
}
