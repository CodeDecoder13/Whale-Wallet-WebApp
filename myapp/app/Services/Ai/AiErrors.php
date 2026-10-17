<?php

namespace App\Services\Ai;

use DomainException;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Throwable;

/** Provider errors can carry request details, so the client only ever sees these fixed sentences. */
class AiErrors
{
    public static function isQuota(Throwable $e): bool
    {
        return $e instanceof RateLimitedException
            || $e instanceof InsufficientCreditsException
            || preg_match('/\b429\b|quota|rate.?limit|RESOURCE_EXHAUSTED/i', $e->getMessage()) === 1;
    }

    public static function message(Throwable $e): string
    {
        return match (true) {
            self::isQuota($e) => 'The AI is busy or out of free quota right now. Try again in a minute, or add the expense manually.',
            $e instanceof DomainException => 'The AI is not set up on this server yet. Add the expense manually.',
            default => 'The AI could not read that. Try again, or add the expense manually.',
        };
    }
}
