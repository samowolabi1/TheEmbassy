<?php

namespace App\Support;

use Closure;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Caches generated pictures (share cards, birthday cards, app icons).
 *
 * They're stored as base64 text, because on shared hosting the cache is a
 * MySQL table whose text column refuses raw image bytes.
 */
class ImageCache
{
    /**
     * @param  Closure(): string  $make  returns the image bytes
     */
    public static function remember(string $key, DateTimeInterface|int|null $ttl, Closure $make): string
    {
        $key = 'img64:'.$key;
        $encoded = $ttl === null
            ? Cache::rememberForever($key, fn () => base64_encode($make()))
            : Cache::remember($key, $ttl, fn () => base64_encode($make()));

        $bytes = base64_decode((string) $encoded, true);
        if ($bytes === false) {
            // Something else was stored under this key; make it again.
            Cache::forget($key);

            return $make();
        }

        return $bytes;
    }
}
