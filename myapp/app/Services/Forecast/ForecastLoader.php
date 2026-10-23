<?php

namespace App\Services\Forecast;

use App\Models\User;

/** Reads a user's data and hands plain arrays to ForecastService. */
class ForecastLoader
{
    public function __construct(private ForecastService $forecast) {}

    /**
     * @param  int|null  $skipId  recurring item to leave out ("what if I cancel it")
     *
     * @see ForecastService::forecast() for the output shape
     */
    public function for(User $user, ?int $skipId = null): array
    {
        // ponytail: credit cards are left out of balance, history and limits; their spending is a bill, not cash.
        $cards = $user->cards()->where('type', '!=', 'credit')->get();
        $today = today();
        $monthStart = now()->startOfMonth();

        $expenses = $user->transactions()->where('type', 'expense')->whereIn('card_id', $cards->modelKeys());
        $limits = [];
        foreach ($cards->whereNotNull('monthly_limit') as $card) {
            $spent = (int) (clone $expenses)->where('card_id', $card->id)->where('occurred_at', '>=', $monthStart)->sum('amount');
            $limits[$card->id] = max(0, $card->monthly_limit - $spent);
        }

        return $this->forecast->forecast([
            'balance' => (int) $cards->sum('balance'),
            'today' => $today,
            'pay_dates' => $user->pay_dates ?? [],
            'outflows' => $user->recurringItems()->active()->get()
                ->reject(fn ($item) => $item->id === $skipId)
                ->flatMap(fn ($item) => array_map(
                    fn ($d) => ['date' => $d->toDateString(), 'amount' => $item->amount],
                    // golden rule: a bill that is overdue or due today and not yet paid is not counted; it joins the numbers once paid
                    $item->occurrences($today->addDay(), $today->addDays(35)),
                ))->values()->all(),
            'spend' => (clone $expenses)->where('occurred_at', '>=', $today->subDays(30))->where('occurred_at', '<', $today)
                ->get(['card_id', 'amount', 'occurred_at'])
                ->map(fn ($t) => ['date' => $t->occurred_at->toDateString(), 'card' => $t->card_id, 'amount' => $t->amount])
                ->all(),
            'card_limits' => $limits,
            'pod_monthly' => (int) $user->pods()->sum('monthly_amount'),
        ]);
    }
}
