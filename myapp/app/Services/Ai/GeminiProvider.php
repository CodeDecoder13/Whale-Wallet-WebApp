<?php

namespace App\Services\Ai;

use App\Contracts\AiProvider;
use DomainException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Enums\Lab;

use function Laravel\Ai\agent;

class GeminiProvider implements AiProvider
{
    private const INSTRUCTIONS = <<<'TXT'
You are a friendly budgeting assistant for a user in the Philippines.
Write 3 to 5 short tips (one or two sentences each) based ONLY on the JSON figures in the prompt.
Quote peso amounts and percentages exactly as given. Never calculate, round, estimate or invent any number.
Do not mention merchants or brands. This is budgeting guidance, not financial advice.
TXT;

    public function recommend(array $aggregates): array
    {
        // the key stays in server config; it never goes to Inertia or the browser
        if (! config('ai.providers.gemini.key')) {
            throw new DomainException('GEMINI_API_KEY is not set. Add it to .env and try again.');
        }

        $response = agent(
            instructions: self::INSTRUCTIONS,
            schema: fn (JsonSchema $schema) => [
                'tips' => $schema->array()->items($schema->string())->required(),
            ],
        )->prompt(json_encode($aggregates), provider: Lab::Gemini, model: config('services.gemini.model'));

        return array_values(array_filter($response['tips'] ?? [], 'is_string'));
    }
}
