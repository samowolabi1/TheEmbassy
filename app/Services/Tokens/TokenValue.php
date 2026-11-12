<?php

namespace App\Services\Tokens;

/**
 * Converting between tokens and naira at the rate the pastor in charge sets (₦2 a token to start).
 * Only used to price things in tokens and to show values; tokens are never paid out as money.
 */
class TokenValue
{
    public static function nairaPerToken(): float
    {
        return max(0.01, (float) config('tokens.naira_per_token', 2));
    }

    /**
     * Tokens needed for an amount in kobo, rounded up so the church is never short.
     */
    public static function tokensFor(int $kobo): int
    {
        return (int) ceil($kobo / (100 * self::nairaPerToken()));
    }

    /**
     * "₦2" or "₦0.50", for one token.
     */
    public static function rateLabel(): string
    {
        $rate = self::nairaPerToken();

        return '₦'.(floor($rate) == $rate ? number_format($rate) : number_format($rate, 2));
    }

    /**
     * What a number of tokens is worth, e.g. "₦300".
     */
    public static function nairaLabel(int $tokens): string
    {
        return '₦'.number_format($tokens * self::nairaPerToken());
    }
}
