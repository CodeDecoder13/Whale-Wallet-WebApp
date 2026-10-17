<?php

namespace App\Services\Forecast;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

/**
 * Pure projection of the cash balance from today to the next payday. No DB, no auth.
 * All money is integer centavos.
 *
 * Each series point is the balance at the START of that date: bills due on a date are
 * already taken out, and one day of average spending is taken out per day passed.
 * The window ends at the next payday (that day's income is not modelled).
 */
class ForecastService
{
    /** Trailing history: weights for days 1-7, 8-14, 15-21 and 22-30 ago. Recent weeks count more. */
    public const WEEK_WEIGHTS = [4, 3, 2, 1];

    /** Days in each of those buckets (the last one is longer so the history is 30 days). */
    private const WEEK_DAYS = [7, 7, 7, 9];

    /**
     * @param  array{
     *     balance: int,
     *     today: CarbonInterface|string,
     *     pay_dates: list<int>,
     *     outflows: list<array{date: string, amount: int}>,
     *     spend: list<array{date: string, card: int, amount: int}>,
     *     card_limits: array<int, int>,
     *     pod_monthly: int,
     * } $in  balance: cash on hand; pay_dates: days of the month; outflows: known bills by due date;
     *        spend: expense history of the last 30 days; card_limits: card id => limit left this month;
     *        pod_monthly: total monthly pod contributions
     * @return array{payday: string, days: int, series: list<array{date: string, balance: int}>, lowest: array{date: string, amount: int}, at_payday: int, safe_per_day: int}
     */
    public function forecast(array $in): array
    {
        $today = Date::parse($in['today'])->startOfDay();
        $payDates = $in['pay_dates'];
        $payday = self::nextPayday($today, $payDates);
        $days = (int) $today->diffInDays($payday);

        $balance = $in['balance'];
        if (self::isPayday($today, $payDates) && $payDates) {
            // the money is already in the balance today, so set aside the pods' share of this pay
            $balance -= intdiv($in['pod_monthly'], count($payDates));
        }

        $daily = $this->dailySpend($today, $in['spend']);
        $bills = [];
        foreach ($in['outflows'] as $o) {
            $bills[$o['date']] = ($bills[$o['date']] ?? 0) + $o['amount'];
        }

        $series = [];
        $due = 0;
        for ($i = 0; $i <= $days; $i++) {
            $date = $today->addDays($i)->toDateString();
            $due += $bills[$date] ?? 0;
            $series[] = ['date' => $date, 'balance' => $balance - $due - $this->spentBy($i, $daily, $in['card_limits'])];
        }

        $lowest = $series[0];
        foreach ($series as $point) {
            if ($point['balance'] < $lowest['balance']) {
                $lowest = $point;
            }
        }

        return [
            'payday' => $payday->toDateString(),
            'days' => $days,
            'series' => $series,
            'lowest' => ['date' => $lowest['date'], 'amount' => $lowest['balance']],
            'at_payday' => $series[$days]['balance'],
            'safe_per_day' => max(0, intdiv($lowest['balance'], $days)),
        ];
    }

    /** First pay date strictly after $today; days beyond a short month land on its last day. */
    public static function nextPayday(CarbonInterface $today, array $payDates): CarbonInterface
    {
        $candidates = [];
        foreach ([0, 1] as $m) {
            $month = $today->startOfMonth()->addMonths($m);
            foreach ($payDates ?: [1] as $d) {
                $candidates[] = $month->day(min($d, $month->daysInMonth));
            }
        }
        sort($candidates);

        return collect($candidates)->first(fn ($c) => $c->gt($today));
    }

    private static function isPayday(CarbonInterface $today, array $payDates): bool
    {
        return in_array($today->day, array_map(fn ($d) => min($d, $today->daysInMonth), $payDates), true);
    }

    /**
     * Weighted average spend per day, by card, over the 30 days before today.
     * Weighted averages are additive, so summing cards equals averaging per category.
     *
     * @return array<int, int>
     */
    private function dailySpend(CarbonInterface $today, array $spend): array
    {
        $weighted = [];
        foreach ($spend as $s) {
            $ago = (int) Date::parse($s['date'])->startOfDay()->diffInDays($today);
            if ($ago >= 1 && $ago <= 30) {
                $weighted[$s['card']] = ($weighted[$s['card']] ?? 0) + $s['amount'] * self::WEEK_WEIGHTS[min(3, intdiv($ago - 1, 7))];
            }
        }

        $den = array_sum(array_map(fn ($w, $n) => $w * $n, self::WEEK_WEIGHTS, self::WEEK_DAYS));

        return array_map(fn ($w) => intdiv(2 * $w + $den, 2 * $den), $weighted); // rounded half up
    }

    /** Spending after $i days; a card with a limit cannot spend more than the limit it has left. */
    private function spentBy(int $i, array $daily, array $limits): int
    {
        $total = 0;
        foreach ($daily as $card => $avg) {
            $total += min($avg * $i, $limits[$card] ?? PHP_INT_MAX);
        }

        return $total;
    }
}
