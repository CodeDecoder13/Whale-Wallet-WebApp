<?php

namespace App\Actions;

use App\Contracts\AiProvider;
use App\Models\User;
use App\Services\Forecast\ForecastLoader;
use Illuminate\Support\Facades\Date;
use Throwable;

/**
 * Chat questions. The model only picks a question key and a date range; the numbers come from
 * the fixed queries below, and the model (or the fake) only words them. Its sentence must pass
 * the same number validator as the tips, otherwise a plain server sentence is used.
 */
class AnswerQuestion
{
    public function __construct(private AiProvider $ai, private ForecastLoader $forecast) {}

    /** @param  array{key?: string, category?: ?string, from?: ?string, to?: ?string, period?: ?string}  $q */
    public function handle(User $user, string $text, array $q): string
    {
        $facts = $this->facts($user, $q);

        if (! $facts) {
            return 'I could not work out which numbers you meant. Try "how much did I spend on Food this week?".';
        }

        try {
            $reply = trim($this->ai->phrase($text, $facts));
        } catch (Throwable $e) {
            report($e); // quota or outage: the plain sentence below is just as correct

            return self::sentence($facts);
        }

        return $reply !== '' && GenerateRecommendations::citesOnlyKnownNumbers($reply, $facts) ? $reply : self::sentence($facts);
    }

    /** Pesos as floats so the validator accepts them. Null when the question cannot be answered. */
    private function facts(User $user, array $q): ?array
    {
        $key = $q['key'] ?? '';
        $pesos = fn (int $centavos) => round($centavos / 100, 2);

        if ($key === 'safe_to_spend') {
            $f = $this->forecast->for($user);

            return ['question' => $key, 'safe_per_day' => $pesos($f['safe_per_day']), 'days_to_payday' => $f['days']];
        }

        if ($key === 'subscriptions_monthly') {
            $total = $user->recurringItems()->where('kind', 'subscription')->get()->sum(fn ($i) => $i->monthlyAmount());

            return ['question' => $key, 'monthly' => $pesos($total)];
        }

        if (! in_array($key, ['spending_by_category', 'total_spent'], true)) {
            return null;
        }

        try {
            $from = Date::parse((string) ($q['from'] ?? ''))->startOfDay();
            $to = Date::parse((string) ($q['to'] ?? ''))->endOfDay();
        } catch (Throwable) {
            return null;
        }

        if ($from > $to || $from->diffInDays($to) > 366) {
            return null;
        }

        $spent = $user->transactions()->where('type', 'expense')->whereBetween('occurred_at', [$from, $to]);
        $period = trim((string) ($q['period'] ?? ''));
        $facts = ['question' => $key, 'period' => $period === '' || preg_match('/\d/', $period) ? 'in that period' : mb_substr($period, 0, 30)];

        if ($key === 'spending_by_category') {
            $category = $user->categories()->whereRaw('lower(name) = ?', [mb_strtolower((string) ($q['category'] ?? ''))])->first();

            if (! $category) {
                return null;
            }

            $facts['category'] = $category->name;
            $spent->where('category_id', $category->id);
        }

        return $facts + ['total' => $pesos((int) $spent->sum('amount'))];
    }

    /** The server-built answer; also what the fake provider says. */
    public static function sentence(array $f): string
    {
        $peso = fn (float|int $n) => '₱'.number_format($n, 2);

        return match ($f['question']) {
            'spending_by_category' => "You spent {$peso($f['total'])} on {$f['category']} {$f['period']}.",
            'total_spent' => "You spent {$peso($f['total'])} {$f['period']}.",
            'safe_to_spend' => "You can spend about {$peso($f['safe_per_day'])} a day for the next {$f['days_to_payday']} days.",
            default => "Your subscriptions cost {$peso($f['monthly'])} a month.",
        };
    }
}
