<?php

namespace App\Http\Controllers;

use App\Enums\RecurringKind;
use App\Models\RecurringItem;
use App\Models\Transaction;
use App\Services\Forecast\ForecastLoader;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ForecastController extends Controller
{
    /** ?skip=<subscription id> recomputes the forecast as if that subscription were cancelled. */
    public function __invoke(Request $request, ForecastLoader $loader): Response
    {
        $skip = $request->integer('skip') ?: null;

        return Inertia::render('forecast/Index', [
            'forecast' => $loader->for($request->user(), $skip),
            'skipped' => $skip,
            // only bills and subscriptions not yet paid this month: skipping a paid one would change nothing
            'subscriptions' => RecurringItem::active()->whereIn('kind', [RecurringKind::Bill, RecurringKind::Subscription])
                ->whereNotIn('id', Transaction::where('type', 'expense')->where('occurred_at', '>=', now()->startOfMonth())->whereNotNull('recurring_item_id')->select('recurring_item_id'))
                ->orderBy('name')->get(['id', 'name', 'amount']),
        ]);
    }
}
