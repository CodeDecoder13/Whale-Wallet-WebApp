<?php

namespace App\Services;

use App\Models\Card;
use App\Models\Device;
use App\Models\RecurringItem;
use App\Models\User;
use App\Services\Forecast\ForecastLoader;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Bill and budget push notifications through Expo's HTTP API. Runs from scheduled commands, so there is
 * no signed-in user: the BelongsToUser scope is off and every query filters user_id by hand.
 * A sent-marker in the cache keeps the same alert from going out twice
 * (ponytail: clearing the cache can repeat an alert once; move markers to a table if that matters).
 */
class PushAlerts
{
    private const URL = 'https://exp.host/--/api/v2/push/send';

    /** Bills due today or in the next 2 days, once per item and due date. Returns how many alerts were sent. */
    public function bills(): int
    {
        $sent = 0;

        foreach ($this->devicesByUser() as $userId => $tokens) {
            $items = RecurringItem::withoutGlobalScopes()->where('user_id', $userId)
                ->whereBetween('next_due_on', [today(), today()->addDays(2)->endOfDay()])->orderBy('next_due_on')->get();

            foreach ($items as $item) {
                $marker = "push:bill:{$item->id}:{$item->next_due_on->toDateString()}";
                $when = match ((int) today()->diffInDays($item->next_due_on)) {
                    0 => 'today', 1 => 'tomorrow', default => 'in 2 days',
                };

                $sent += (int) $this->deliver('bill_due', $marker, $tokens, 'Bill due '.$when, "{$item->name}: ".self::peso($item->amount));
            }
        }

        return $sent;
    }

    /** Cards at 80% of the monthly limit, or over it, once per card and month (going over sends a second alert). */
    public function budgets(): int
    {
        $sent = 0;

        foreach ($this->devicesByUser() as $userId => $tokens) {
            $cards = Card::withoutGlobalScopes()->where('user_id', $userId)->whereNotNull('monthly_limit')->where('monthly_limit', '>', 0)->withSpent()->get();

            foreach ($cards as $card) {
                $spent = (int) $card->spent;
                $level = match (true) {
                    $spent > $card->monthly_limit => 'over',
                    $spent * 100 >= $card->monthly_limit * 80 => '80',
                    default => null,
                };

                if ($level) {
                    $body = self::peso($spent).' of '.self::peso($card->monthly_limit).' spent this month.';
                    $title = $level === 'over' ? "{$card->name} is over its monthly limit" : "{$card->name} is at 80% of its monthly limit";
                    $sent += (int) $this->deliver('budget_warning', 'push:budget:'.$card->id.':'.now()->format('Y-m').':'.$level, $tokens, $title, $body);
                }
            }
        }

        return $sent;
    }

    /** One push a day at 1 or 2 days before payday ("payday_near"). */
    public function payday(ForecastLoader $loader): int
    {
        $sent = 0;

        foreach ($this->devicesByUser() as $userId => $tokens) {
            $f = $loader->for(User::withoutGlobalScopes()->findOrFail($userId));

            if ($f["days"] <= 2) {
                $sent += (int) $this->deliver("payday_near", "push:payday:{$userId}:{$f["payday"]}:{$f["days"]}", $tokens, "Payday in {$f["days"]} ".($f["days"] === 1 ? "day" : "days"), "Payday is {$f["payday"]}.");
            }
        }

        return $sent;
    }

    /** Once a day: the dashboard's safe-to-spend ("daily_safe_to_spend"). */
    public function daily(ForecastLoader $loader): int
    {
        $sent = 0;

        foreach ($this->devicesByUser() as $userId => $tokens) {
            $safe = $loader->for(User::withoutGlobalScopes()->findOrFail($userId))["safe_per_day"];
            $sent += (int) $this->deliver("daily_safe_to_spend", "push:daily:{$userId}:".today()->toDateString(), $tokens, "You can spend ".self::peso($safe)." today", "Safe to spend until your next payday.");
        }

        return $sent;
    }

    /** @return Collection<int, Collection<int, string>> user id => Expo tokens */
    private function devicesByUser()
    {
        return Device::withoutGlobalScopes()->get(['user_id', 'expo_push_token'])->groupBy('user_id')->map->pluck('expo_push_token');
    }

    /** Sends one alert to every token unless its marker is set; the marker is set only after Expo took the batch. */
    private function deliver(string $type, string $marker, $tokens, string $title, string $body): bool
    {
        if (Cache::has($marker)) {
            return false;
        }

        foreach ($tokens->chunk(100) as $batch) {
            $response = Http::acceptJson()->post(self::URL, $batch->map(fn ($to) => ['to' => $to, 'title' => $title, 'body' => $body, 'sound' => 'default', 'data' => ['type' => $type]])->values()->all());

            if ($response->failed()) {
                return false; // retried on the next run
            }

            // tickets come back in request order; a token Expo no longer knows is dropped
            foreach ($batch->values() as $i => $token) {
                if ($response->json("data.$i.details.error") === 'DeviceNotRegistered') {
                    Device::withoutGlobalScopes()->where('expo_push_token', $token)->delete();
                }
            }
        }

        Cache::put($marker, true, now()->addDays(35));

        return true;
    }

    private static function peso(int $centavos): string
    {
        return 'PHP '.number_format($centavos / 100, 2);
    }
}
