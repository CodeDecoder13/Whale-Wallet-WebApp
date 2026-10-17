<?php

namespace App\Actions;

use App\Contracts\AiProvider;
use App\Http\Requests\PesoRequest;
use App\Models\ChatThread;
use App\Models\RecurringItem;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Throwable;

/**
 * One chat turn. "Jollibee 250" is parsed by rules with no AI call. Everything else goes to the
 * provider, which only classifies; expense drafts are stored on the message and saved by the
 * confirm endpoint, questions are answered by AnswerQuestion.
 */
class HandleChatMessage
{
    public function __construct(private AiProvider $ai, private ParseQuickEntry $quick, private AnswerQuestion $answer) {}

    public static function isSalary(string $text): bool
    {
        return ! str_contains($text, '?') && ! preg_match('/^\s*(how|what|show|when|why|where)\b/i', $text)
            && preg_match('/\b(salary|sweldo|sahod|payroll|paycheck|got paid|income|bonus|allowance|commission|freelance|13th month)\b/i', $text) === 1;
    }

    /** @return array{id: int, reply: string, draft?: array, quick_replies?: list<string>} */
    public function handle(User $user, ChatThread $thread, string $text): array
    {
        $last = $thread->messages()->where('role', 'assistant')->latest('id')->first()?->draft_json;
        $pending = $last && $last['missing'] && empty($last['saved']) ? $last : null;

        // an unsaved loan draft (even a complete one) takes follow-ups like a new date; the expense flow never sees it
        $pendingLoan = ($last['kind'] ?? null) === 'loan' && empty($last['saved']) ? $last : null;
        // an income chat waits for its amount, then for the proof screenshot
        $incomeDraft = ($last['kind'] ?? null) === 'income' ? $last : null;
        $pending = $pendingLoan || $incomeDraft ? null : $pending;

        $fields = null;
        $loan = null;
        $reply = null;

        // rules first: short, no question mark, ends in an amount
        $salary = self::isSalary($text);
        $amountAnswer = $incomeDraft && in_array('amount', $incomeDraft['missing'], true) ? self::amountFrom($text) : null;
        $income = null;
        $quick = ! $salary && ! str_contains($text, '?') && str_word_count($text) <= 5 && ! preg_match('/\b(loan|utang|borrowed)\b/i', $text) ? $this->quick->handle($text) : null;

        if ($salary || $amountAnswer) {
            $income = self::incomeDraft($amountAnswer ?? self::amountFrom($text));
            $reply = $income['amount']
                ? 'Got it, ₱'.number_format($income['amount'] / 100, 2).'. Attach a screenshot of the transfer with the image button. I will read the reference number, date and amount, check it against what you told me, then you confirm it from Add income on your dashboard.'
                : 'Nice! How much did you receive?';
        } elseif ($pendingLoan && ($update = self::loanUpdate($pendingLoan, $text))) {
            $loan = $this->loanDraft($update);
        } elseif ($quick) {
            $fields = ['merchant' => $quick['merchant'], 'amount' => $quick['amount'], 'card' => $quick['card']];
        } else {
            $r = $this->ai->chat($text, $this->context($user, $pending));

            if ($r['intent'] === 'log_expense') {
                $d = $r['draft'] ?? [];
                $fields = array_filter([
                    'merchant' => $d['merchant'] ?? null,
                    'amount' => self::centavos($d['amount'] ?? null),
                    'card' => $d['card'] ?? null,
                    'category' => $d['category'] ?? null,
                    'occurred_at' => $d['occurred_at'] ?? null,
                ], fn ($v) => $v !== null && $v !== '') + ($pending ?? []);
            } elseif ($r['intent'] === 'add_loan') {
                $d = $r['draft'] ?? [];
                $loan = $this->loanDraft(['merchant' => $d['merchant'] ?? null, 'amount' => self::centavos($d['amount'] ?? null), 'payments' => self::payments((string) ($d['payments'] ?? '')), 'occurred_at' => $d['occurred_at'] ?? null]);
            } elseif ($r['intent'] === 'question') {
                $reply = $this->answer->handle($user, $text, $r['question'] ?? []);
            } else {
                $reply = trim($r['reply']);
                // chat replies may not contain figures: nothing here is computed from the user's data
                $reply = $reply !== '' && GenerateRecommendations::citesOnlyKnownNumbers($reply, []) ? $reply : 'I can log an expense (try "Jollibee 250") or answer questions about your spending.';
            }
        }

        $draft = $income ?? $loan ?? ($fields !== null ? $this->draft($user, $fields) : null);
        $quickReplies = [];

        if ($draft && ! $income) {
            [$reply, $quickReplies] = $this->wording($user, $draft);
        }

        // saved only after the turn worked, so a failed AI call can be retried without a duplicate
        $thread->messages()->create(['role' => 'user', 'content' => $text]);
        $message = $thread->messages()->create(['role' => 'assistant', 'content' => $reply, 'draft_json' => $draft]);

        return ['id' => $message->id, 'reply' => $reply] + ($draft ? ['draft' => $draft] : []) + ($quickReplies ? ['quick_replies' => $quickReplies] : []);
    }

    private function context(User $user, ?array $pending): array
    {
        return [
            'today' => today()->toDateString(),
            'categories' => $user->categories()->pluck('name')->all(),
            'cards' => $user->cards()->pluck('name')->all(),
            'pending' => $pending ? ['merchant' => $pending['merchant'], 'amount' => $pending['amount'] ? $pending['amount'] / 100 : null, 'card' => $pending['card'], 'category' => $pending['category'], 'missing' => $pending['missing']] : null,
        ];
    }

    /** Resolves names to this user's cards and categories. A lone card is the default. */
    private function draft(User $user, array $f): array
    {
        $byName = fn ($rows, ?string $name) => $name === null ? null : $rows->first(fn ($r) => mb_strtolower($r->name) === mb_strtolower($name));
        $cards = $user->cards()->get(['id', 'name']);
        $card = $byName($cards, $f['card'] ?? null) ?? ($cards->count() === 1 ? $cards->first() : null);
        $category = $byName($user->categories()->get(['id', 'name']), $f['category'] ?? null);

        try {
            $at = Date::parse($f['occurred_at'] ?? 'today')->toDateString();
        } catch (Throwable) {
            $at = today()->toDateString();
        }

        $draft = [
            'merchant' => ($f['merchant'] ?? '') !== '' ? mb_substr($f['merchant'], 0, 120) : null,
            'amount' => ($f['amount'] ?? 0) > 0 ? (int) $f['amount'] : null,
            'card_id' => $card?->id, 'card' => $card?->name,
            'category_id' => $category?->id, 'category' => $category?->name,
            'occurred_at' => $at,
        ];
        $draft['missing'] = array_keys(array_filter(['merchant' => ! $draft['merchant'], 'amount' => ! $draft['amount'], 'card' => ! $card]));

        return $draft;
    }

    /** Server-written, so the amount shown is the amount saved. One follow-up at most. */
    private function wording(User $user, array $d): array
    {
        if (($d['kind'] ?? null) === 'loan') {
            $n = $d['payments'];

            return match ($d['missing'][0] ?? null) {
                'merchant' => ['Who is the loan with?', []],
                'amount' => ['How much is the loan?', []],
                'due' => ['When is the first payment due? Give a date like Oct 30, or just the day of the month.', []],
                'payments' => ['Can it be paid once, or in 2 or 3 payments? For longer, pick 6 or 12 months.', ['Pay once', '2 payments', '3 payments', '6 months', '12 months']],
                default => ['Add a loan of ₱'.number_format($d['amount'] / 100, 2)." to {$d['merchant']}, paid ".($n === 1 ? 'once' : "over $n monthly payments")." starting {$d['occurred_at']}? Check the details and tap Save.", []],
            };
        }

        return match ($d['missing'][0] ?? null) {
            'merchant' => ['Where did you spend it?', []],
            'amount' => ['How much was it?', []],
            'card' => ['Which card should I charge?', $user->cards()->orderBy('name')->pluck('name')->all()],
            default => ['Log ₱'.number_format($d['amount'] / 100, 2)." at {$d['merchant']} on {$d['card']}? Check the details and tap Save.", []],
        };
    }

    /** Pesos in the text as centavos: "25000", "₱25,000.50" or "25k"; ordinals like 13th are not amounts. */
    private static function amountFrom(string $text): ?int
    {
        $text = preg_replace('/\b\d+(?:st|nd|rd|th)\b/i', '', $text);

        if (! preg_match('/(\d[\d,]*(?:\.\d+)?)\s*(k)?\b/iu', $text, $m)) {
            return null;
        }

        $pesos = (float) str_replace(',', '', $m[1]) * (empty($m[2]) ? 1 : 1000);

        return $pesos > 0 ? (int) round($pesos * 100) : null;
    }

    /** Held on the chat message so the proof upload that follows can be checked against the amount. */
    private static function incomeDraft(?int $amount): array
    {
        return [
            'kind' => 'income', 'amount' => $amount, 'missing' => $amount ? [] : ['amount'],
            'merchant' => null, 'card_id' => null, 'card' => null, 'category_id' => null, 'category' => null,
            'occurred_at' => today()->toDateString(),
        ];
    }

    /** Loan terms the Bills page offers: once, 2, 3 payments, or 6 or 12 months. */
    private static function payments(string $text): ?int
    {
        $n = preg_match('/\b(once|one time|one)\b/i', $text) ? 1 : (preg_match('/\d+/', $text, $m) ? (int) $m[0] : null);

        return in_array($n, [1, 2, 3, 6, 12], true) ? $n : null;
    }

    /** Answers the question the draft is waiting on, or changes its date; null when the text is neither. */
    private static function loanUpdate(array $d, string $text): ?array
    {
        $f = ['merchant' => $d['merchant'], 'amount' => $d['amount'], 'payments' => $d['payments'], 'occurred_at' => $d['occurred_at']];

        if (($d['missing'][0] ?? null) === 'payments' && ($n = self::payments($text))) {
            return [...$f, 'payments' => $n];
        }

        return ($due = self::dueDate($text)) ? [...$f, 'occurred_at' => $due] : null;
    }

    /** Finds a date inside the text: "Oct 30", "make it 5 Nov", "next friday", "2026-11-05", "30/10", "on the 30th" or just "30". */
    private static function dueDate(string $text): ?string
    {
        $mon = '(?:jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|june?|july?|aug(?:ust)?|sep(?:t(?:ember)?)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)';
        $day = '(?:mon(?:day)?|tue(?:s(?:day)?)?|wed(?:nesday)?|thu(?:r(?:s(?:day)?)?)?|fri(?:day)?|sat(?:urday)?|sun(?:day)?)';
        $n = '\\d{1,2}(?:st|nd|rd|th)?';

        if (preg_match("/^\\s*($n)\\s*$|\\b(?:on|the|every)\\s+($n)\\b(?!\\s*(?:payments?|months?|times))/i", $text, $m) && ($d = (int) ($m[1] ?: $m[2])) >= 1 && $d <= 31) {
            return RecurringItem::nextDue($d)->toDateString();
        }

        $found = preg_match("/\\b(\\d{4}-\\d{2}-\\d{2}|\\d{1,2}\\/\\d{1,2}(?:\\/\\d{2,4})?|$mon\\s+$n(?:,?\\s+\\d{4})?|$n\\s+$mon(?:,?\\s+\\d{4})?|tomorrow|today|next\\s+(?:$day|week|month)|$day)\\b/i", $text, $m);

        try {
            $d = $found ? Date::parse($m[1]) : null;
        } catch (Throwable) {
            return null;
        }

        // a month and day with no year means the next one
        return $d ? ($d->lt(today()) && ! preg_match('/\\d{4}/', $m[1]) ? $d->addYear() : $d)->toDateString() : null;
    }

    /** A loan draft is saved as a recurring item (kind debt) by the confirm endpoint. */
    private function loanDraft(array $f): array
    {
        // the due date is asked for, never defaulted to today
        try {
            $at = ($f['occurred_at'] ?? '') !== '' ? Date::parse($f['occurred_at'])->toDateString() : null;
        } catch (Throwable) {
            $at = null;
        }

        $draft = [
            'kind' => 'loan',
            'merchant' => ($f['merchant'] ?? '') !== '' ? mb_substr($f['merchant'], 0, 80) : null,
            'amount' => ($f['amount'] ?? 0) > 0 ? (int) $f['amount'] : null,
            'payments' => $f['payments'] ?? null,
            'occurred_at' => $at,
            'card_id' => null, 'card' => null, 'category_id' => null, 'category' => null,
        ];
        $draft['missing'] = array_keys(array_filter(['merchant' => ! $draft['merchant'], 'amount' => ! $draft['amount'], 'due' => ! $draft['occurred_at'], 'payments' => ! $draft['payments']]));

        return $draft;
    }

    private static function centavos(?string $pesos): ?int
    {
        $n = PesoRequest::centavos(str_replace(',', '', (string) $pesos));

        return is_int($n) ? $n : null;
    }
}
