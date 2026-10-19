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
    public function __construct(private AiProvider $ai, private ForecastLoader $forecast, private FinancialSnapshot $snapshot) {}

    /** @param  array{key?: string, category?: ?string, from?: ?string, to?: ?string, period?: ?string}  $q */
    public function handle(User $user, string $text, array $q, array $history = []): string
    {
        $facts = $this->facts($user, $q);

        if (! $facts) {
            return $this->advise($user, $text, $history);
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
            $total = $user->recurringItems()->active()->where('kind', 'subscription')->get()->sum(fn ($i) => $i->monthlyAmount());

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

    /**
     * Everything that is not one of the fixed questions: net worth, advice, "can I afford it".
     * The model sees the whole snapshot and may quote any figure in it, but a reply with a number
     * that is not in the snapshot is replaced by a plain server sentence.
     *
     * @param  list<array{role: string, content: string}>  $history
     */
    public function advise(User $user, string $text, array $history = []): string
    {
        $facts = $this->snapshot->for($user);
        $peso = fn (float $n) => '₱'.number_format($n, 2);
        $plain = "Your net worth is {$peso($facts['net_worth']['total'])}, and you can spend about {$peso($facts['safe_to_spend_per_day'])} a day until payday. Ask me about your bills, cards, savings or spending.";

        try {
            $reply = trim($this->ai->advise($text, $facts, $history));
        } catch (Throwable $e) {
            report($e); // quota or outage: the plain sentence is still true

            return $plain;
        }

        return $reply !== '' && GenerateRecommendations::citesOnlyKnownNumbers($reply, $facts) ? $reply : $plain;
    }
}
