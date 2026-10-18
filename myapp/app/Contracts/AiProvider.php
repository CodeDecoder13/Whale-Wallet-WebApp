<?php

namespace App\Contracts;

interface AiProvider
{
    /**
     * Turn budget aggregates (no merchants, no raw transactions) into short tips.
     *
     * @param  array<string, mixed>  $aggregates  amounts in pesos, percents as numbers
     * @return list<string>
     */
    public function recommend(array $aggregates): array;

    /**
     * Read a receipt image. Raw model output: strings, "" when a field is not on the receipt.
     * The caller validates it; nothing here is trusted yet.
     *
     * @param  list<string>  $categories  the user's own category names
     * @return array{merchant: string, amount: string, currency: string, occurred_at: string, reference: string, category: string, payment_app: string, confidence: float}
     */
    public function parseReceipt(string $imagePath, array $categories): array;

    /**
     * Classify one chat message. Never computes numbers: for a question it only names
     * which fixed server function to run and over which dates.
     *
     * @param  array{today: string, categories: list<string>, cards: list<string>, pending: ?array}  $context  pending = unfinished expense draft
     * @return array{intent: string, reply: string, draft: ?array{merchant: ?string, amount: ?string, category: ?string, card: ?string, occurred_at: ?string}, question: ?array{key: string, category: ?string, from: ?string, to: ?string, period: ?string}}
     */
    public function chat(string $text, array $context): array;

    /** Word an answer using only the numbers in $facts. The caller validates the result. */
    public function phrase(string $question, array $facts): string;

    /**
     * Free-form coaching: net worth, advice, "can I afford it". Uses ONLY the numbers in $facts
     * (see FinancialSnapshot); the caller validates the result. $history is the last few turns.
     *
     * @param  list<array{role: string, content: string}>  $history
     */
    public function advise(string $text, array $facts, array $history): string;
}
