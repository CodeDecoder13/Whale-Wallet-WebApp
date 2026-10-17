<?php

namespace App\Actions;

use App\Contracts\AiProvider;
use App\Http\Requests\PesoRequest;
use App\Models\ChatThread;
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
    public const SALARY_PROMPT = 'Nice! Attach a screenshot of the salary transfer with the image button. I will read the reference number, date and amount, then you confirm it from Add income on your dashboard.';

    public function __construct(private AiProvider $ai, private ParseQuickEntry $quick, private AnswerQuestion $answer) {}

    public static function isSalary(string $text): bool
    {
        return ! str_contains($text, '?') && preg_match('/\b(salary|sweldo|sahod|payroll|paycheck|got paid)\b/i', $text) === 1;
    }

    /** @return array{id: int, reply: string, draft?: array, quick_replies?: list<string>} */
    public function handle(User $user, ChatThread $thread, string $text): array
    {
        $pending = $thread->messages()->where('role', 'assistant')->latest('id')->first();
        $pending = $pending?->draft_json && $pending->draft_json['missing'] && empty($pending->draft_json['saved']) ? $pending->draft_json : null;

        $fields = null;
        $reply = null;

        // rules first: short, no question mark, ends in an amount
        $salary = self::isSalary($text);
        $quick = ! $salary && ! str_contains($text, '?') && str_word_count($text) <= 5 ? $this->quick->handle($text) : null;

        if ($salary) {
            $reply = self::SALARY_PROMPT;
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
            } elseif ($r['intent'] === 'question') {
                $reply = $this->answer->handle($user, $text, $r['question'] ?? []);
            } else {
                $reply = trim($r['reply']);
                // chat replies may not contain figures: nothing here is computed from the user's data
                $reply = $reply !== '' && GenerateRecommendations::citesOnlyKnownNumbers($reply, []) ? $reply : 'I can log an expense (try "Jollibee 250") or answer questions about your spending.';
            }
        }

        $draft = $fields !== null ? $this->draft($user, $fields) : null;
        $quickReplies = [];

        if ($draft) {
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
        return match ($d['missing'][0] ?? null) {
            'merchant' => ['Where did you spend it?', []],
            'amount' => ['How much was it?', []],
            'card' => ['Which card should I charge?', $user->cards()->orderBy('name')->pluck('name')->all()],
            default => ['Log ₱'.number_format($d['amount'] / 100, 2)." at {$d['merchant']} on {$d['card']}? Check the details and tap Save.", []],
        };
    }

    private static function centavos(?string $pesos): ?int
    {
        $n = PesoRequest::centavos(str_replace(',', '', (string) $pesos));

        return is_int($n) ? $n : null;
    }
}
