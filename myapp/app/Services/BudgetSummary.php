<?php

namespace App\Services;

use App\Models\IncomeSource;
use App\Models\RecurringItem;
use App\Models\User;

class BudgetSummary
{
    /**
     * Monthly figures in centavos.
     * ponytail: sums amounts as-is, assumes every income/recurring row is monthly; normalise by cadence in M2 forecast.
     *
     * @return array{income: int, fixed: int, remaining: int}
     */
    public function for(User $user): array
    {
        $income = (int) IncomeSource::withoutGlobalScopes()->where('user_id', $user->id)->sum('amount');
        $fixed = (int) RecurringItem::withoutGlobalScopes()->where('user_id', $user->id)->sum('amount');

        return ['income' => $income, 'fixed' => $fixed, 'remaining' => $income - $fixed];
    }
}
