<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Church details saved in Settings & roles. Each key is a config key (see config/settings.php),
 * and saved values replace the defaults in config/church.php and config/content/.
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    private const CACHE_KEY = 'settings.all';

    /**
     * Every saved setting, cached.
     *
     * @return array<string, string|null>
     */
    public static function values(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => static::query()->pluck('value', 'key')->all());
    }

    /**
     * Put saved settings over the config defaults. Quietly does nothing before the table exists.
     */
    public static function applyToConfig(): void
    {
        try {
            $saved = static::values();
        } catch (Throwable) {
            return;
        }

        $allowed = static::keys();
        foreach ($saved as $key => $value) {
            if (in_array($key, $allowed, true) && $value !== null && $value !== '') {
                config([$key => $value]);
            }
        }
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public static function putMany(array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * The config keys the Settings page may change.
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        return collect(config('settings'))->flatMap(fn ($group) => array_keys($group['fields']))->all();
    }
}
