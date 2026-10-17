<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CardResource;
use App\Http\Resources\PodResource;
use App\Http\Resources\RecommendationResource;
use App\Http\Resources\RecurringItemResource;
use App\Models\Card;
use App\Models\Pod;
use App\Models\Recommendation;
use App\Models\RecurringItem;
use App\Models\Transaction;
use App\Services\BudgetSummary;
use App\Services\Forecast\ForecastLoader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class DashboardController extends Controller
{
    public function dashboard(Request $request, ForecastLoader $loader, BudgetSummary $budget): JsonResponse
    {
        $forecast = self::withBalance($loader->for($request->user()));
        $spent = (int) Transaction::where('type', 'expense')->where('occurred_at', '>=', now()->startOfMonth())->sum('amount');
        $left = $budget->for($request->user())['remaining'] - $spent;

        return response()->json([
            'safe_to_spend_today' => $forecast['safe_per_day'],
            'forecast' => Arr::except($forecast, 'series'),
            'left_this_month' => $left,
            'next_payday' => $forecast['payday'],
            'days_to_payday' => $forecast['days'],
            'net_worth' => \App\Http\Controllers\DashboardController::netWorth(),
            'cards' => CardResource::collection(Card::withSpent()->orderBy('name')->get())->resolve(),
            'pods' => PodResource::collection(Pod::orderBy('name')->get())->resolve(),
            'upcoming_bills' => RecurringItemResource::collection(
                RecurringItem::whereBetween('next_due_on', [today(), today()->addDays(14)->endOfDay()])->orderBy('next_due_on')->get()
            )->resolve(),
            'recommendations' => RecommendationResource::collection(Recommendation::whereNull('dismissed_at')->latest('id')->limit(3)->get())->resolve(),
        ]);
    }

    /** ?skip=<recurring item id> projects the balance as if that item were cancelled. */
    public function forecast(Request $request, ForecastLoader $loader): JsonResponse
    {
        $skip = $request->validate(['skip' => ['nullable', 'integer']])['skip'] ?? null;

        $skip = $skip === null ? null : (int) $skip;

        return response()->json(self::withBalance($loader->for($request->user(), $skip)) + ['skipped' => $skip]);
    }

    /** The mobile app reads lowest.balance; the engine's name for it is lowest.amount. */
    private static function withBalance(array $forecast): array
    {
        $forecast['lowest']['balance'] = $forecast['lowest']['amount'];

        return $forecast;
    }
}
