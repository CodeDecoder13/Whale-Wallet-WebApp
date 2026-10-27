<?php

namespace App\Http\Controllers;

use App\Enums\CardType;
use App\Enums\RecurringKind;
use App\Models\Card;
use App\Models\Pod;
use App\Models\IncomeSource;
use App\Models\Receipt;
use App\Models\Recommendation;
use App\Models\RecurringItem;
use App\Models\Transaction;
use App\Services\BudgetSummary;
use App\Services\Forecast\ForecastLoader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Centavos. Assets: cash, bank and e-wallet balances plus savings pods. Debts: loans still to pay
     * (what is left to pay on each) and what is owed on credit cards (a credit card balance goes negative as you spend).
     *
     * @return array{assets: int, debts: int, total: int}
     */
    public static function netWorth(): array
    {
        $cards = Card::get(['type', 'balance']);
        $credit = (int) $cards->where('type', CardType::Credit)->sum('balance');
        $loans = (int) RecurringItem::where('kind', RecurringKind::Debt)->whereNull('hidden_at')->whereNotNull('installments')->get()
            ->sum(fn ($i) => $i->remaining());

        $assets = (int) $cards->where('type', '!=', CardType::Credit)->sum('balance') + (int) Pod::sum('saved_amount') + max(0, $credit);
        $debts = $loans + max(0, -$credit);

        return ['assets' => $assets, 'debts' => $debts, 'total' => $assets - $debts];
    }

    public function __invoke(Request $request, BudgetSummary $budget, ForecastLoader $loader): Response|RedirectResponse
    {
        if (! Card::exists()) {
            return to_route('onboarding.show');
        }

        // transfers and income are excluded: only expenses count as spending
        $month = fn () => Transaction::where('type', 'expense')->where('occurred_at', '>=', now()->startOfMonth());
        $spent = (int) $month()->sum('amount');
        $paidFixed = (int) $month()->whereNotNull('recurring_item_id')->sum('amount');

        // spending per day for the last 7 days, oldest first, for the mini chart on the Spent tile
        $byDay = Transaction::where('type', 'expense')->where('occurred_at', '>=', today()->subDays(6))->get(['amount', 'occurred_at'])
            ->groupBy(fn ($t) => $t->occurred_at->toDateString())->map->sum('amount');
        $week = collect(range(6, 0))->map(fn ($n) => ['day' => today()->subDays($n)->format('D'), 'amount' => (int) ($byDay[today()->subDays($n)->toDateString()] ?? 0)]);

        $summary = $budget->for($request->user());
        $forecast = $loader->for($request->user());

        return Inertia::render('Dashboard', [
            'net_worth' => self::netWorth(),
            // fixed = every bill, subscription and loan (feeds safe to spend); paid ones are already inside spent, so left to spend = income - spent
            'summary' => [...$summary, 'paid_fixed' => $paidFixed, 'spent' => $spent, 'safe' => $summary['income'] - $spent],
            'week' => $week,
            'income_entries' => IncomeSource::where('month', now()->format('Y-m'))->orderBy('id')->get()
                ->map(fn ($e) => [...$e->only(['id', 'name', 'kind', 'amount', 'reference']), 'received_on' => $e->received_on?->toDateString()]),
            'income_proofs' => Receipt::where('purpose', 'income')->where('status', '!=', 'confirmed')->latest('id')->limit(3)->get()->map->toClient()->values(),
            'welcome' => (bool) $request->session()->pull('welcome', false),
            'consented' => (bool) $request->user()->ai_consent_at,
            'cards' => Card::orderBy('name')->get(['id', 'name']), // which card receives income
            'forecast' => $forecast,
            'tips' => Recommendation::whereNull('dismissed_at')->latest('id')->limit(3)->get(['id', 'text']),
            'categories' => $month()->with('category:id,name')
                ->selectRaw('category_id, sum(amount) as total')->groupBy('category_id')->orderByDesc('total')->get()
                ->map(fn ($r) => ['name' => $r->category?->name ?? 'Uncategorized', 'total' => (int) $r->total]),
            'upcoming' => RecurringItem::active()->where('next_due_on', '<=', today()->addDays(14)->endOfDay())
                ->where(fn ($q) => $q->whereNull('installments')->orWhere('installments', '>', 0)) // paid-off loans keep an old date
                ->orderBy('next_due_on')->get(['id', 'name', 'amount', 'next_due_on', 'logo']),
        ]);
    }
}
