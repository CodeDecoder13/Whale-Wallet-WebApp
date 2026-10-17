<?php

namespace App\Actions;

class ParseQuickEntry
{
    /**
     * "Jollibee 250" -> merchant + centavos. A trailing word ("coffee 95 gcash") is a card-name hint.
     * Regex only; returns null when there is no merchant or no positive amount.
     *
     * @return array{merchant: string, amount: int, card: ?string}|null
     */
    public function handle(string $text): ?array
    {
        if (! preg_match('/^(.*?[^\W\d].*?)\s+(\d[\d,]*(?:\.\d{1,2})?)(?:\s+(\S+))?$/u', trim($text), $m)) {
            return null;
        }

        $amount = (int) round((float) str_replace(',', '', $m[2]) * 100);

        return $amount > 0 ? ['merchant' => $m[1], 'amount' => $amount, 'card' => $m[3] ?? null] : null;
    }
}
