<?php

namespace App\Http\Controllers;

use App\Enums\RecurringKind;
use App\Models\RecurringItem;
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
            'subscriptions' => RecurringItem::where('kind', RecurringKind::Subscription)->orderBy('name')->get(['id', 'name', 'amount']),
        ]);
    }
}
