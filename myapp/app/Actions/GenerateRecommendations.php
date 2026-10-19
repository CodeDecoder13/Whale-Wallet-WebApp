<?php

namespace App\Actions;

use App\Contracts\AiProvider;
use App\Models\Recommendation;
use App\Models\User;
use App\Services\BudgetSummary;
use App\Services\Forecast\ForecastLoader;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GenerateRecommendations
{
    public function __construct(
        private AiProvider $ai,
        private BudgetSummary $budget,
        private ForecastLoader $forecast,
    ) {}

    /**
     * Sends aggregates only, keeps the tips whose numbers all come from them.
     *
     * @return Collection<int, Recommendation>
     */
    public function handle(User $user): Collection
    {
        if (! $user->ai_consent_at) {
            throw new AuthorizationException('Accept the AI notice before generating tips.');
        }

        $input = $this->aggregates($user);
        $tips = $this->ai->recommend($input);

        if (count($tips) < 3 || count($tips) > 5) {
            throw new DomainException('The AI returned '.count($tips).' tips, expected 3 to 5.');
        }

        $valid = array_values(array_filter($tips, fn ($tip) => self::citesOnlyKnownNumbers($tip, $input)));

        if (! $valid) {
            throw new DomainException('Every tip used a number that was not in your data, so none were saved.');
        }

        return DB::transaction(function () use ($user, $valid, $input) {
            $user->recommendations()->whereNull('dismissed_at')->delete();

            return collect($valid)->map(fn ($tip) => $user->recommendations()->create(['text' => $tip, 'data_json' => $input]));
        });
    }

    /** Pesos and percents only: no merchants, notes or individual transactions. */
    private function aggregates(User $user): array
    {
        $pesos = fn (int $centavos) => round($centavos / 100, 2);

        $summary = $this->budget->for($user);
        $forecast = $this->forecast->for($user);

        $categories = $user->transactions()->where('type', 'expense')->where('occurred_at', '>=', now()->startOfMonth())
            ->selectRaw('category_id, sum(amount) as total')->groupBy('category_id')->orderByDesc('total')
            ->with('category:id,name')->get();
        $spent = (int) $categories->sum('total');

        return [
            'income' => $pesos($summary['income']),
            'fixed' => $pesos($summary['fixed']),
            'remaining' => $pesos($summary['remaining']),
            'spent_this_month' => $pesos($spent),
            'categories' => $categories->map(fn ($c) => [
                'name' => $c->category?->name ?? 'Uncategorized',
                'amount' => $pesos((int) $c->total),
                'percent' => $spent ? (int) round($c->total / $spent * 100) : 0,
            ])->all(),
            'safe_to_spend_per_day' => $pesos($forecast['safe_per_day']),
            'days_to_payday' => $forecast['days'],
            'lowest_point' => $pesos($forecast['lowest']['amount']),
            'subscriptions_monthly' => $pesos($user->recurringItems()->active()->where('kind', 'subscription')->get()->sum(fn ($i) => $i->monthlyAmount())),
        ];
    }

    /** Every number in the tip (1,250 / 1250 / ₱1,250.00 alike) must appear somewhere in $input. */
    public static function citesOnlyKnownNumbers(string $tip, array $input): bool
    {
        $norm = fn ($n) => number_format((float) str_replace(',', '', (string) $n), 2, '.', '');

        $known = [];
        array_walk_recursive($input, function ($v) use (&$known, $norm) {
            if (is_int($v) || is_float($v)) {
                $known[$norm($v)] = true;
            }
        });

        preg_match_all('/\d+(?:,\d{3})*(?:\.\d+)?/', $tip, $found);

        foreach ($found[0] as $n) {
            if (! isset($known[$norm($n)])) {
                return false;
            }
        }

        return true;
    }
}
