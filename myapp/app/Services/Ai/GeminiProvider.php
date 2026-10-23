<?php

namespace App\Services\Ai;

use App\Contracts\AiProvider;
use DomainException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Files\Audio;
use Laravel\Ai\Files\Image;

use function Laravel\Ai\agent;

class GeminiProvider implements AiProvider
{
    private const INSTRUCTIONS = <<<'TXT'
You are a friendly budgeting assistant for a user in the Philippines.
Write 3 to 5 short tips (one or two sentences each) based ONLY on the JSON figures in the prompt.
Quote peso amounts and percentages exactly as given. Never calculate, round, estimate or invent any number.
Do not mention merchants or brands. This is budgeting guidance, not financial advice.
TXT;

    private const RECEIPT = <<<'TXT'
You read screenshots and photos of payment receipts from Philippine e-wallets and banks (GCash, Maya, BPI, BDO and similar).
Extract the payment shown. Copy values exactly as printed; never guess. Use "" for anything not visible.
merchant: who was paid (store, biller or recipient name), not the app or bank used to pay.
amount: the total paid in pesos as a plain decimal string such as "1250.50" (no currency sign, no thousands separators). Use the final total debited, including fees; not a balance, change or discount line.
occurred_at: the payment date and time as ISO 8601 (YYYY-MM-DD HH:MM), "" if no date is visible.
reference: the reference or transaction number. currency: ISO code, "PHP" for pesos.
category: pick exactly one name from the allowed categories in the prompt, or "" if none fits.
payment_app: the app or bank the payment was made with (for example GCash), "" if unclear.
confidence: 0 to 1, how sure you are that merchant, amount and date are right.
TXT;

    private const CHAT = <<<'TXT'
You are Whale Chat, the assistant of a budgeting app for users in the Philippines. Classify the user's message. Never calculate or invent numbers.
intent log_expense: the user is recording a purchase. Fill merchant, amount (pesos, plain decimal string), category and card ONLY when the user said them or the pending draft has them; "" otherwise. occurred_at as YYYY-MM-DD only if stated.
  If a "pending" draft is given, the message probably answers its missing fields: return just those fields.
intent add_loan: the user says they owe money to someone or something and must repay it (loan, utang, borrowed). merchant = who it is owed to or a short name, amount in pesos, loan_payments = how many payments they said (1, 2, 3, 6 or 12; "one time"/"once" is 1), occurred_at = first payment date as YYYY-MM-DD only if stated. "" for anything not said.
intent question: the user asks for ONE of the exact figures below. Set question_key to one of spending_by_category, total_spent, safe_to_spend, subscriptions_monthly. For the first two set from and to (YYYY-MM-DD, using "today" in the prompt) and period (a short phrase with no digits, like "this week"). For spending_by_category set question_category to one of the allowed categories. You do NOT answer questions.
intent manage: the user wants to add, change, cancel, renew or delete a bill, subscription or loan, edit or delete an expense they already logged, or sends more than one request in one message ("log lunch 200 and pay Netflix"). Leave reply as "".
intent chat: anything else, including net worth, balances, bills, savings, loans, advice, "can I afford", greetings and small talk. Leave reply as "": another step answers it with the user's data.
All fields are strings; use "" when not applicable. category and card must match the allowed names exactly.
TXT;

    private const ACTIONS = <<<'TXT'
You turn the user's message into 1 to 4 independent actions for a budgeting app (Philippines, pesos). Never calculate or invent numbers.
Allowed types: add_recurring, edit_recurring, cancel_subscription, renew_subscription, delete_recurring, edit_transaction, delete_transaction, log_expense, add_loan, pay_bill.
Pick targets ONLY by id from the "items" (bills, subscriptions, loans) and "transactions" lists in the prompt; target_id 0 when none matches or the type needs none.
add_recurring: name, kind ("bill" or "subscription"), amount, cadence (weekly, biweekly, monthly, quarterly or yearly), next_due_on (YYYY-MM-DD, using "today" in the prompt for relative dates).
edit_recurring / edit_transaction: fill ONLY the fields the user wants changed ("" for the rest). amount is pesos as a plain decimal string.
cancel_subscription: reason = the reason they gave, "" if none. renew_subscription, delete_recurring, delete_transaction: just the target.
log_expense: merchant, amount, category and card (exact allowed names) only when said, occurred_at only when stated. add_loan: merchant, amount, payments (1, 2, 3, 6 or 12), next_due_on = first payment date. pay_bill: target_id of the item, amount only if they said it, card only if said.
Actions are independent: do not rely on one action's result in another. Use "" for any field not said.
TXT;

    private const PHRASE = <<<'TXT'
Answer the user's question in one short friendly sentence using ONLY the numbers in the JSON facts.
Quote peso amounts exactly as given with a peso sign. Never calculate, round, estimate or add any other number or date.
TXT;

    private const ADVISE = <<<'TXT'
You are Whale, a friendly money coach inside a budgeting app for a user in the Philippines. Reply in the user's language (English, Tagalog or Taglish).
The JSON "facts" is everything you know about their money right now (pesos). "history" is the recent conversation.
Answer the user's message and give practical, specific advice grounded in the facts: net worth, bills due soon, card limits, savings pods, loans, spending by category, what is safe to spend.
Rules: use ONLY numbers that appear in facts, quoted exactly with a peso sign. Never calculate, round, estimate, compare with a new figure or invent any number.
Do not write dates: say "in N days" only when N is in facts. Write other small counts as words (two, three).
If the question needs data you do not have (for example a purchase price), ask for it or explain what you would need, without making up a figure.
Be concise: two to five short sentences. End with one clear next step when advice is asked. You are not a licensed financial adviser; do not add a disclaimer unless they ask about investing or tax.
TXT;

    public function recommend(array $aggregates): array
    {
        $this->requireKey();

        $response = agent(
            instructions: self::INSTRUCTIONS,
            schema: fn (JsonSchema $schema) => [
                'tips' => $schema->array()->items($schema->string())->required(),
            ],
        )->prompt(json_encode($aggregates), provider: Lab::Gemini, model: config('services.gemini.model'));

        return array_values(array_filter($response['tips'] ?? [], 'is_string'));
    }

    public function parseReceipt(string $imagePath, array $categories): array
    {
        $this->requireKey();

        $r = agent(
            instructions: self::RECEIPT,
            schema: fn (JsonSchema $s) => [
                'merchant' => $s->string()->required(),
                'amount' => $s->string()->required(),
                'currency' => $s->string()->required(),
                'occurred_at' => $s->string()->required(),
                'reference' => $s->string()->required(),
                'category' => $s->string()->required(),
                'payment_app' => $s->string()->required(),
                'confidence' => $s->number()->min(0)->max(1)->required(),
            ],
        )->prompt(
            'Allowed categories: '.json_encode($categories),
            attachments: [Image::fromPath($imagePath)],
            provider: Lab::Gemini,
            model: config('services.gemini.receipt_model'),
        );

        return [
            'merchant' => (string) ($r['merchant'] ?? ''),
            'amount' => (string) ($r['amount'] ?? ''),
            'currency' => (string) ($r['currency'] ?? ''),
            'occurred_at' => (string) ($r['occurred_at'] ?? ''),
            'reference' => (string) ($r['reference'] ?? ''),
            'category' => (string) ($r['category'] ?? ''),
            'payment_app' => (string) ($r['payment_app'] ?? ''),
            'confidence' => (float) ($r['confidence'] ?? 0),
        ];
    }

    public function chat(string $text, array $context): array
    {
        $this->requireKey();

        $r = agent(
            instructions: self::CHAT,
            schema: fn (JsonSchema $s) => [
                'intent' => $s->string()->enum(['log_expense', 'add_loan', 'question', 'manage', 'chat'])->required(),
                'reply' => $s->string()->required(),
                'merchant' => $s->string()->required(),
                'amount' => $s->string()->required(),
                'category' => $s->string()->required(),
                'card' => $s->string()->required(),
                'occurred_at' => $s->string()->required(),
                'loan_payments' => $s->string()->required(),
                'question_key' => $s->string()->required(),
                'question_category' => $s->string()->required(),
                'from' => $s->string()->required(),
                'to' => $s->string()->required(),
                'period' => $s->string()->required(),
            ],
        )->prompt(json_encode(['message' => $text, ...$context]), provider: Lab::Gemini, model: config('services.gemini.model'));

        $v = fn (string $k) => ($r[$k] ?? '') === '' ? null : (string) $r[$k];

        return [
            'intent' => (string) ($r['intent'] ?? 'chat'),
            'reply' => (string) ($r['reply'] ?? ''),
            'draft' => ['merchant' => $v('merchant'), 'amount' => $v('amount'), 'category' => $v('category'), 'card' => $v('card'), 'occurred_at' => $v('occurred_at'), 'payments' => $v('loan_payments')],
            'question' => ['key' => (string) $v('question_key'), 'category' => $v('question_category'), 'from' => $v('from'), 'to' => $v('to'), 'period' => $v('period')],
        ];
    }

    public function actions(string $text, array $context): array
    {
        $this->requireKey();

        $fields = ['name', 'kind', 'amount', 'cadence', 'next_due_on', 'reason', 'merchant', 'card', 'category', 'occurred_at', 'payments'];
        $r = agent(
            instructions: self::ACTIONS,
            schema: fn (JsonSchema $s) => [
                'actions' => $s->array()->items($s->object([
                    'type' => $s->string()->required(),
                    'target_id' => $s->integer()->required(),
                    ...array_map(fn () => $s->string()->required(), array_flip($fields)),
                ])->withoutAdditionalProperties())->required(),
            ],
        )->prompt(json_encode(['message' => $text, ...$context]), provider: Lab::Gemini, model: config('services.gemini.model'));

        return array_values(array_filter(array_map(fn ($a) => is_array($a) ? $a : null, $r['actions'] ?? []))); // max four; the caller trims
    }

    public function phrase(string $question, array $facts): string
    {
        $this->requireKey();

        return trim(agent(instructions: self::PHRASE)
            ->prompt(json_encode(['question' => $question, 'facts' => $facts]), provider: Lab::Gemini, model: config('services.gemini.model'))->text);
    }

    public function transcribe(string $audioPath, string $mime): string
    {
        $this->requireKey();

        return trim(agent(instructions: 'Transcribe the speech exactly as spoken. It may be English, Tagalog or Taglish; keep the words in the language spoken. Output only the transcript, nothing else. If there is no speech, output nothing.')
            ->prompt('Transcribe this audio.', attachments: [Audio::fromPath($audioPath, $mime)], provider: Lab::Gemini, model: config('services.gemini.model'))->text);
    }

    /** The key stays in server config; it never goes to Inertia or the browser. */
    private function requireKey(): void
    {
        if (! config('ai.providers.gemini.key')) {
            throw new DomainException('GEMINI_API_KEY is not set. Add it to .env and try again.');
        }
    }

    public function advise(string $text, array $facts, array $history): string
    {
        $this->requireKey();

        return trim(agent(instructions: self::ADVISE)
            ->prompt(json_encode(['message' => $text, 'facts' => $facts, 'history' => $history]), provider: Lab::Gemini, model: config('services.gemini.model'))->text);
    }
}
