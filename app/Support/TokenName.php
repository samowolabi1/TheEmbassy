<?php

namespace App\Support;

/**
 * What the church calls its reward currency ("Crowns" to start). The pastor in charge renames it
 * in Settings & roles, and every page, email and message follows. In Blade it's available as
 * TokenName::many(), TokenName::count(5) and so on.
 */
class TokenName
{
    /** "Crown" */
    public static function one(): string
    {
        return self::clean(config('tokens.name.one'), 'Crown');
    }

    /** "Crowns" */
    public static function many(): string
    {
        return self::clean(config('tokens.name.many'), 'Crowns');
    }

    /** "Crowns", with a capital at the start of a sentence even if it's set in lower case. */
    public static function title(): string
    {
        return self::ucfirst(self::many());
    }

    public static function titleOne(): string
    {
        return self::ucfirst(self::one());
    }

    /** "1 Crown", "5 Crowns" */
    public static function count(int|float $n): string
    {
        return number_format($n).' '.($n == 1 ? self::one() : self::many());
    }

    /** The little picture shown beside amounts: 👑 to start. */
    public static function icon(): string
    {
        return self::clean(config('tokens.name.icon'), '👑');
    }

    private static function clean(mixed $value, string $default): string
    {
        $value = trim((string) $value);

        return $value === '' ? $default : $value;
    }

    private static function ucfirst(string $value): string
    {
        return mb_strtoupper(mb_substr($value, 0, 1)).mb_substr($value, 1);
    }
}
