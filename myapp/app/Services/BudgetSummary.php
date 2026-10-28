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
        $base = $salary->exists() ? (int) $salary->sum('amount') : ($this->waiting($user) ? 0 : (int) $user->incomeSources()->whereNull('month')->sum('amount'));
        $income = $base + (int) $month('side')->sum('amount'); // side income never replaces the baseline
        $fixed = $user->recurringItems()->active()->get()->sum(fn ($item) => $item->monthlyAmount());

        return ['income' => $income, 'fixed' => $fixed, 'remaining' => $income - $fixed];
    }

    /** Said "not paid yet this month" at sign-up: the baseline salary only counts from income_from on. */
    private function waiting(User $user): bool
    {
        return $user->income_from && $user->income_from > now()->format('Y-m');
    }

    /** The baseline salary still to come while waiting for the first pay, else null. */
    public function nextIncome(User $user): ?int
    {
        return $this->waiting($user) ? (int) $user->incomeSources()->whereNull('month')->sum('amount') : null;
    }
}
