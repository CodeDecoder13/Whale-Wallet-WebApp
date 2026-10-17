<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\RecurringItem;
use App\Models\Transaction;
use App\Services\BudgetSummary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, BudgetSummary $budget): Response|RedirectResponse
    {
        if (! Card::exists()) {
            return to_route('onboarding.show');
        }

        // transfers and income are excluded: only expenses count as spending
        $month = fn () => Transaction::where('type', 'expense')->where('occurred_at', '>=', now()->startOfMonth());
        $spent = (int) $month()->sum('amount');

        // ponytail: simple version (income - fixed - spent so far); M2 ForecastService replaces it.
        $summary = $budget->for($request->user());
        $safe = $summary['remaining'] - $spent;
        $daysLeft = now()->daysInMonth - now()->day + 1;

        return Inertia::render('Dashboard', [
            'summary' => [...$summary, 'spent' => $spent, 'safe' => $safe, 'daily' => max(0, intdiv($safe, $daysLeft))],
            'categories' => $month()->with('category:id,name')
                ->selectRaw('category_id, sum(amount) as total')->groupBy('category_id')->orderByDesc('total')->get()
                ->map(fn ($r) => ['name' => $r->category?->name ?? 'Uncategorized', 'total' => (int) $r->total]),
            'upcoming' => RecurringItem::whereBetween('next_due_on', [today(), today()->addDays(14)->endOfDay()])
                ->orderBy('next_due_on')->get(['id', 'name', 'amount', 'next_due_on']),
        ]);
    }
}
