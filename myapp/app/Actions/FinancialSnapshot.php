<?php

namespace App\Actions;

use App\Http\Controllers\DashboardController;
use App\Models\Card;
use App\Models\Pod;
use App\Models\User;
use App\Services\BudgetSummary;
use App\Services\Forecast\ForecastLoader;

/**
 * Everything Whale Chat may know about the user, as pesos and counts. Names are the user's own labels
 * (cards, pods, bills); no individual transactions, merchants or notes. Dates are given as "days from now"
 * so the number guard can check every figure the model quotes.
 */
class FinancialSnapshot
{
    public function __construct(private BudgetSummary $budget, private ForecastLoader $forecast) {}

    public function for(User $user): array
    {
        $p = fn (int $centavos) => round($centavos / 100, 2);
        $summary = $this->budget->for($user);
        $f = $this->forecast->for($user);
        $worth = DashboardController::netWorth();

        $month = fn () => $user->transactions()->where('type', 'expense');
        $spent = (int) $month()->where('occurred_at', '>=', now()->startOfMonth())->sum('amount');
        $lastMonth = (int) $month()->whereBetween('occurred_at', [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()])->sum('amount');

        $categories = $month()->where('occurred_at', '>=', now()->startOfMonth())->with('category:id,name')
            ->selectRaw('category_id, sum(amount) as total')->groupBy('category_id')->orderByDesc('total')->limit(6)->get()
            ->map(fn ($c) => ['name' => $c->category?->name ?? 'Uncategorized', 'amount' => $p((int) $c->total), 'percent_of_spending' => $spent ? (int) round($c->total / $spent * 100) : 0]);

        $items = $user->recurringItems()->active()->orderBy('next_due_on')->get();

        return [
            'net_worth' => ['assets' => $p($worth['assets']), 'debts' => $p($worth['debts']), 'total' => $p($worth['total'])],
            'monthly_income' => $p($summary['income']),
            'monthly_bills_and_subscriptions' => $p($summary['fixed']),
            'left_after_bills' => $p($summary['remaining']),
            'spent_this_month' => $p($spent),
            'spent_last_month' => $p($lastMonth),
            'left_this_month' => $p($summary['remaining'] - $spent),
            'safe_to_spend_per_day' => $p($f['safe_per_day']),
            'days_to_payday' => $f['days'],
            'balance_at_payday' => $p($f['at_payday']),
            'lowest_balance_before_payday' => $p($f['lowest']['amount']),
            'cards' => Card::withSpent()->orderBy('name')->get()->map(fn ($c) => [
                'name' => $c->name, 'type' => $c->type->value, 'balance' => $p($c->balance),
                'monthly_limit' => $c->monthly_limit ? $p($c->monthly_limit) : null,
                'spent_this_month' => $p((int) $c->spent),
                'limit_used_percent' => $c->monthly_limit ? (int) round($c->spent / $c->monthly_limit * 100) : null,
            ])->all(),
            'savings_pods' => Pod::orderBy('name')->get()->map(fn ($x) => [
                'name' => $x->name, 'saved' => $p($x->saved_amount), 'target' => $p($x->target_amount), 'monthly_contribution' => $p($x->monthly_amount),
                'percent_done' => $x->target_amount ? min(100, intdiv($x->saved_amount * 100, $x->target_amount)) : 0,
            ])->all(),
            'spending_by_category_this_month' => $categories->all(),
            'bills_and_subscriptions' => $items->where('kind', '!=', 'debt')->take(12)->map(fn ($i) => [
                'name' => $i->name, 'amount' => $p($i->amount), 'kind' => $i->kind->value, 'due_in_days' => max(0, (int) today()->diffInDays($i->next_due_on, false)),
            ])->values()->all(),
            // fully paid loans are archived: named here so the chat can say they are done, never counted as owed
            'completed_loans' => $items->where('kind', 'debt')->filter(fn ($i) => $i->installments !== null && ! $i->paymentsLeft())->pluck('name')->values()->all(),
            'loans' => $items->where('kind', 'debt')->filter(fn ($i) => $i->installments === null || $i->paymentsLeft())->map(fn ($i) => [
                'name' => $i->name, 'payment' => $p($i->amount), 'payments_left' => $i->installments === null ? null : $i->paymentsLeft(),
                'still_owed' => $i->installments === null ? null : $p($i->amount * $i->paymentsLeft()),
            ])->values()->all(),
        ];
    }
}
