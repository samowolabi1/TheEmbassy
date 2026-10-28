<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Text and photos for the About and home pages, edited in admin.
 * Anything not yet saved falls back to config/content/{key}.php.
 */
#[Fillable(['key', 'content'])]
class PageContent extends Model
{
    public const PAGES = ['about', 'home'];

    protected static function booted(): void
    {
        static::saved(fn (PageContent $page) => Cache::forget("page-content.{$page->key}"));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['content' => 'array'];
    }

    /**
     * The page's content: saved values over the config defaults.
     *
     * @return array<string, mixed>
     */
    public static function for(string $key): array
    {
        try {
            $saved = Cache::rememberForever("page-content.{$key}", fn () => static::where('key', $key)->value('content'));
        } catch (Throwable) {
            $saved = null; // before migrations run
        }
        if (is_string($saved)) {
            $saved = json_decode($saved, true);
        }

        return array_replace(config("content.{$key}", []), is_array($saved) ? $saved : []);
    }

    /**
     * @param  array<string, mixed>  $content
     */
    public static function put(string $key, array $content): void
    {
        static::updateOrCreate(['key' => $key], ['content' => array_replace(static::for($key), $content)]);
    }

    public static function photoUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }
}
