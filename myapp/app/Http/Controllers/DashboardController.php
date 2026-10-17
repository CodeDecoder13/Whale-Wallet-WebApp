<?php

namespace App\Http\Controllers;

use App\Models\Card;
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
    public function __invoke(Request $request, BudgetSummary $budget, ForecastLoader $loader): Response|RedirectResponse
    {
        if (! Card::exists()) {
            return to_route('onboarding.show');
        }

        // transfers and income are excluded: only expenses count as spending
        $month = fn () => Transaction::where('type', 'expense')->where('occurred_at', '>=', now()->startOfMonth());
        $spent = (int) $month()->sum('amount');

        $summary = $budget->for($request->user());
        $forecast = $loader->for($request->user());

        return Inertia::render('Dashboard', [
            'summary' => [...$summary, 'spent' => $spent, 'safe' => $summary['remaining'] - $spent],
            'income_entries' => IncomeSource::where('month', now()->format('Y-m'))->orderBy('id')->get()
                ->map(fn ($e) => [...$e->only(['id', 'name', 'amount', 'reference']), 'received_on' => $e->received_on?->toDateString()]),
            'income_proofs' => Receipt::where('purpose', 'income')->where('status', '!=', 'confirmed')->latest('id')->limit(3)->get()->map->toClient()->values(),
            'consented' => (bool) $request->user()->ai_consent_at,
            'forecast' => $forecast,
            'tips' => Recommendation::whereNull('dismissed_at')->latest('id')->limit(3)->get(['id', 'text']),
            'categories' => $month()->with('category:id,name')
                ->selectRaw('category_id, sum(amount) as total')->groupBy('category_id')->orderByDesc('total')->get()
                ->map(fn ($r) => ['name' => $r->category?->name ?? 'Uncategorized', 'total' => (int) $r->total]),
            'upcoming' => RecurringItem::whereBetween('next_due_on', [today(), today()->addDays(14)->endOfDay()])
                ->orderBy('next_due_on')->get(['id', 'name', 'amount', 'next_due_on']),
        ]);
    }
}
