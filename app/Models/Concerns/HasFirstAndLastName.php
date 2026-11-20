<?php

namespace App\Models\Concerns;

/**
 * A first name and a last name, with the full `name` kept in step for the
 * parts of the app that show or search it. Setting only `name` (imports,
 * older code) fills in the two parts the other way round.
 */
trait HasFirstAndLastName
{
    public static function bootHasFirstAndLastName(): void
    {
        static::saving(function (self $person) {
            if ($person->isDirty(['first_name', 'last_name']) && filled($person->first_name)) {
                $person->first_name = self::tidy($person->first_name);
                $person->last_name = filled($person->last_name) ? self::tidy($person->last_name) : null;
                $person->name = trim($person->first_name.' '.$person->last_name);
            } elseif ($person->isDirty('name') || blank($person->first_name)) {
                [$person->first_name, $person->last_name] = self::split((string) $person->name);
            }
        });
    }

    /**
     * "Sis. Adaeze Obi" → ["Sis. Adaeze", "Obi"]; one word is a first name only.
     *
     * @return array{0: string, 1: ?string}
     */
    public static function split(string $name): array
    {
        $words = preg_split('/\s+/', trim($name)) ?: [];
        if (count($words) < 2) {
            return [trim($name), null];
        }
        $last = array_pop($words);

        return [implode(' ', $words), $last];
    }

    private static function tidy(string $value): string
    {
        return preg_replace('/\s+/', ' ', trim($value));
    }
}
