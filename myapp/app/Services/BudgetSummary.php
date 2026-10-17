<?php

namespace App\Services;

use App\Models\User;

class BudgetSummary
{
    /**
     * Monthly figures in centavos. Recurring items are normalised by cadence.
     * Income is what was received this month if any was logged, else the onboarding baseline.
     *
     * @return array{income: int, fixed: int, remaining: int}
     */
    public function for(User $user): array
    {
        $received = $user->incomeSources()->where('month', now()->format('Y-m'));
        $income = (int) ($received->exists() ? $received : $user->incomeSources()->whereNull('month'))->sum('amount');
        $fixed = $user->recurringItems()->get()->sum(fn ($item) => $item->monthlyAmount());

        return ['income' => $income, 'fixed' => $fixed, 'remaining' => $income - $fixed];
    }
}
