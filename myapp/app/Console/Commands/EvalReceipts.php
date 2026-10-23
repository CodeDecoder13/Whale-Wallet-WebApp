<?php

namespace App\Console\Commands;

use App\Contracts\AiProvider;
use App\Jobs\ParseReceipt;
use App\Models\Category;
use App\Services\Ai\AiErrors;
use App\Services\Ai\GeminiProvider;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * Manual accuracy check: runs tests/Fixtures/receipts/*.png through the configured provider and
 * compares each field with expected.json. This makes real Gemini calls (one per fixture), so it is
 * never part of the test suite. Usage: php artisan receipts:eval [--delay=4]
 */
#[Signature('receipts:eval {--delay=4 : Seconds to wait between calls (free-tier rate limit)}')]
#[Description('Run the receipt fixtures through the real AI provider and print field accuracy (real Gemini calls)')]
class EvalReceipts extends Command
{
    private const FIELDS = ['merchant', 'amount', 'date', 'reference', 'category'];

    public function handle(AiProvider $ai): int
    {
        if ($ai instanceof GeminiProvider && ! config('ai.providers.gemini.key')) {
            $this->error('GEMINI_API_KEY is not set. Add it to .env, then run this again. (Each run makes one real Gemini call per fixture.)');

            return self::FAILURE;
        }

        $dir = base_path('tests/Fixtures/receipts');
        $expected = json_decode(file_get_contents("$dir/expected.json"), true);
        $score = array_fill_keys(self::FIELDS, 0);
        $rows = [];

        foreach (array_keys($expected) as $i => $stem) {
            $want = $expected[$stem];
            $i && $ai instanceof GeminiProvider && sleep((int) $this->option('delay'));

            try {
                $raw = $ai->parseReceipt("$dir/$stem.png", Category::DEFAULTS);
                $got = ParseReceipt::normalize($raw);
            } catch (Throwable $e) {
                report($e);
                $rows[] = [$stem, ...array_fill(0, count(self::FIELDS), 'ERR'), AiErrors::message($e)];

                continue;
            }

            $norm = fn (?string $s) => preg_replace('/[\s-]+/', '', mb_strtolower((string) $s));
            $ok = [
                'merchant' => $norm($got['merchant']) === $norm($want['merchant']),
                'amount' => $got['amount'] === (int) round((float) $want['amount'] * 100),
                'date' => $got['occurred_at'] === substr($want['occurred_at'], 0, 10),
                'reference' => $norm($got['reference']) === $norm($want['reference']),
                'category' => $norm($raw['category']) === $norm($want['category']),
            ];

            foreach ($ok as $field => $good) {
                $score[$field] += $good;
            }
            $rows[] = [$stem, ...array_map(fn ($good) => $good ? 'ok' : 'WRONG', array_values($ok)), ''];
        }

        $this->table(['receipt', ...self::FIELDS, 'note'], $rows);
        $n = count($expected);
        $this->line('Field accuracy ('.config('services.gemini.receipt_model').'): '.implode(', ', array_map(fn ($f) => "$f {$score[$f]}/$n", self::FIELDS)));

        return self::SUCCESS;
    }
}
