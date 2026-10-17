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
}
