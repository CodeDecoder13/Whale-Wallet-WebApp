<?php

namespace App\Services;

use App\Models\User;

class BudgetSummary
{
    /**
     * Monthly figures in centavos. Recurring items are normalised by cadence.
     * ponytail: income is summed as-is; onboarding only writes monthly income.
     *
     * @return array{income: int, fixed: int, remaining: int}
     */
    public function for(User $user): array
    {
        $income = (int) $user->incomeSources()->sum('amount');
        $fixed = $user->recurringItems()->get()->sum(fn ($item) => $item->monthlyAmount());

        return ['income' => $income, 'fixed' => $fixed, 'remaining' => $income - $fixed];
    }
}
