<?php

namespace App\Services\Ai;

use App\Contracts\AiProvider;

/** Deterministic tips built only from the numbers it is given. Used in tests and offline. */
class FakeAiProvider implements AiProvider
{
    public function recommend(array $aggregates): array
    {
        $peso = fn (float|int $n) => '₱'.number_format($n, 2);
        $a = $aggregates;

        $tips = [
            "You can spend about {$peso($a['safe_to_spend_per_day'])} a day for the next {$a['days_to_payday']} days and still reach payday.",
            "Your subscriptions cost {$peso($a['subscriptions_monthly'])} a month. Cancel one you do not use to free up cash.",
            "Fixed bills take {$peso($a['fixed'])} of your {$peso($a['income'])} income, so keep extra spending below {$peso($a['remaining'])}.",
        ];

        if ($top = $a['categories'][0] ?? null) {
            $tips[] = "{$top['name']} is your biggest category at {$peso($top['amount'])}, {$top['percent']}% of what you spent this month.";
        }

        return $tips;
    }
}
