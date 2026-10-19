<?php

namespace App\Services\Ai;

use App\Actions\AnswerQuestion;
use App\Contracts\AiProvider;
use Carbon\CarbonImmutable;

/** Deterministic stand-in used in tests and offline. Never touches the network. */
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

    /** Looks the image up by file name in tests/Fixtures/receipts/expected.json (key contained in the name). */
    public function parseReceipt(string $imagePath, array $categories): array
    {
        $expected = json_decode(file_get_contents(base_path('tests/Fixtures/receipts/expected.json')), true);

        foreach ($expected as $key => $fields) {
            if (str_contains(basename($imagePath), $key)) {
                return $fields;
            }
        }

        return ['merchant' => '', 'amount' => '', 'currency' => '', 'occurred_at' => '', 'reference' => '', 'category' => '', 'payment_app' => '', 'confidence' => 0.0];
    }

    /** Keyword rules standing in for the model. */
    public function chat(string $text, array $context): array
    {
        $t = mb_strtolower($text);
        $today = CarbonImmutable::parse($context['today']);
        $log = fn (array $draft) => ['intent' => 'log_expense', 'reply' => '', 'draft' => $draft + ['merchant' => null, 'amount' => null, 'category' => null, 'card' => null, 'occurred_at' => null], 'question' => null];

        if ($pending = $context['pending'] ?? null) {
            $draft = [];
            foreach ($pending['missing'] as $field) {
                $draft[$field] = match ($field) {
                    'card' => collect($context['cards'])->first(fn ($c) => str_contains($t, mb_strtolower($c))),
                    'amount' => preg_match('/\d[\d,]*(?:\.\d+)?/', $text, $m) ? $m[0] : null,
                    default => trim($text),
                };
            }
            if (array_filter($draft)) {
                return $log($draft);
            }
        }

        if (preg_match('/\b(cancel|renew|resubscribe|delete|remove|change|edit|update|rename)\b|\band (?:pay|paid|log|spent|cancel)\b|\badd\b.*\b(?:bill|subscription)\b/i', $text) && ! str_contains($t, '?')) {
            return ['intent' => 'manage', 'reply' => '', 'draft' => null, 'question' => null];
        }

        if (preg_match('/\b(loan|utang|borrowed)\b/i', $text) && ! str_contains($t, '?')) {
            preg_match('/\bto (?:my )?(\w+)/i', $text, $who);
            preg_match('/(\d[\d,]*(?:\.\d+)?)\s*(k)?\b/i', $text, $amt);
            $amount = isset($amt[1]) ? (string) ((float) str_replace(',', '', $amt[1]) * (empty($amt[2]) ? 1 : 1000)) : null;
            preg_match('/\b(once|one time|\d+ (?:payments|months|times))\b/i', $text, $n);

            return ['intent' => 'add_loan', 'reply' => '', 'draft' => ['merchant' => $who[1] ?? null, 'amount' => $amount, 'category' => null, 'card' => null, 'occurred_at' => null, 'payments' => $n[1] ?? null], 'question' => null];
        }

        if (preg_match('/(?:spent|paid|bought)\s+(?:₱|php\s*)?(\d[\d,]*(?:\.\d+)?)\s+(?:at|on|for)\s+(.+)/iu', $text, $m)) {
            return $log(['amount' => $m[1], 'merchant' => trim($m[2], " .!?\t")]);
        }

        $question = fn (string $key, ?string $category = null) => [
            'intent' => 'question', 'reply' => '', 'draft' => null,
            'question' => $key === 'spending_by_category' || $key === 'total_spent'
                ? ['key' => $key, 'category' => $category, ...$this->range($t, $today)]
                : ['key' => $key, 'category' => null, 'from' => null, 'to' => null, 'period' => null],
        ];

        if (str_contains($t, 'safe to spend')) {
            return $question('safe_to_spend');
        }
        if (str_contains($t, 'subscription')) {
            return $question('subscriptions_monthly');
        }
        if (preg_match('/how much|spent|spend/', $t)) {
            $category = collect($context['categories'])->first(fn ($c) => str_contains($t, mb_strtolower($c)));

            return $question($category ? 'spending_by_category' : 'total_spent', $category);
        }

        return ['intent' => 'chat', 'reply' => 'I can log an expense (try "Jollibee 250") or answer questions like "how much food this week?".', 'draft' => null, 'question' => null];
    }

    /** Keyword rules standing in for the model: one action per clause, split on "and" / "tapos" / "then". */
    public function actions(string $text, array $context): array
    {
        $blank = ['target_id' => 0, 'name' => '', 'kind' => '', 'amount' => '', 'cadence' => '', 'next_due_on' => '', 'reason' => '', 'merchant' => '', 'card' => '', 'category' => '', 'occurred_at' => '', 'payments' => ''];
        $actions = [];

        foreach (array_slice(preg_split('/\s+(?:and|tapos|then)\s+/i', $text), 0, 4) as $clause) {
            $t = mb_strtolower($clause);
            $item = collect($context['items'])->first(fn ($i) => str_contains($t, mb_strtolower($i['name'])));
            $tx = collect($context['transactions'])->first(fn ($x) => str_contains($t, mb_strtolower($x['merchant'])));
            preg_match('/(\d[\d,]*(?:\.\d+)?)/', $clause, $n);
            preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $clause, $d);
            $a = ['amount' => $n[1] ?? '', 'next_due_on' => $d[1] ?? ''] + $blank;

            $actions[] = match (true) {
                (bool) preg_match('/\bcancel/', $t) => ['type' => 'cancel_subscription', 'target_id' => $item['id'] ?? 0, 'reason' => preg_match('/because (.+)$/', $t, $r) ? $r[1] : '', 'amount' => ''] + $a,
                (bool) preg_match('/\b(renew|resubscribe)/', $t) => ['type' => 'renew_subscription', 'target_id' => $item['id'] ?? 0] + $a,
                (bool) preg_match('/\b(delete|remove)/', $t) => $item ? ['type' => 'delete_recurring', 'target_id' => $item['id']] + $a : ['type' => 'delete_transaction', 'target_id' => $tx['id'] ?? 0] + $a,
                (bool) preg_match('/\b(change|edit|update|set)\b/', $t) => $item ? ['type' => 'edit_recurring', 'target_id' => $item['id']] + $a : ['type' => 'edit_transaction', 'target_id' => $tx['id'] ?? 0] + $a,
                (bool) preg_match('/\badd\b/', $t) => ['type' => 'add_recurring', 'name' => preg_match('/add (.+?) (?:bill|subscription)/', $t, $m) ? ucfirst($m[1]) : '', 'kind' => str_contains($t, 'subscription') ? 'subscription' : 'bill', 'cadence' => str_contains($t, 'yearly') ? 'yearly' : 'monthly'] + $a,
                (bool) preg_match('/\b(paid|pay)\b/', $t) && $item => ['type' => 'pay_bill', 'target_id' => $item['id']] + $a,
                default => ['type' => 'log_expense', 'merchant' => preg_match('/(?:log|spent)\s+(?:on\s+)?([a-z ]+?)\s*\d/i', $clause, $m) ? trim($m[1]) : ''] + $a,
            };
        }

        return $actions;
    }

    public function phrase(string $question, array $facts): string
    {
        return AnswerQuestion::sentence($facts);
    }

    /** @return array{from: string, to: string, period: string} */
    private function range(string $t, CarbonImmutable $today): array
    {
        [$from, $to, $period] = match (true) {
            str_contains($t, 'today') => [$today, $today, 'today'],
            str_contains($t, 'last week') => [$today->startOfWeek()->subWeek(), $today->startOfWeek()->subDay(), 'last week'],
            str_contains($t, 'this week') => [$today->startOfWeek(), $today, 'this week'],
            str_contains($t, 'last month') => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth(), 'last month'],
            default => [$today->startOfMonth(), $today, 'this month'],
        };

        return ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'period' => $period];
    }

    public function advise(string $text, array $facts, array $history): string
    {
        $peso = fn (float|int $n) => '₱'.number_format($n, 2);

        return "Your net worth is {$peso($facts['net_worth']['total'])}, and you can spend about {$peso($facts['safe_to_spend_per_day'])} a day until payday.";
    }
}
