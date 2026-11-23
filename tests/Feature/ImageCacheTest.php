<?php

namespace Tests\Feature;

use App\Support\ImageCache;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ImageCacheTest extends TestCase
{
    public function test_pictures_are_cached_as_text_so_a_mysql_cache_table_accepts_them(): void
    {
        $png = "\x89PNG\r\n\x1a\n".random_bytes(64);
        $made = 0;
        $make = function () use ($png, &$made) {
            $made++;

            return $png;
        };

        $this->assertSame($png, ImageCache::remember('card', 60, $make));
        $this->assertSame($png, ImageCache::remember('card', 60, $make));
        $this->assertSame(1, $made);

        $stored = Cache::get('img64:card');
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9+\/=]+$/', $stored);
        $this->assertTrue(mb_check_encoding($stored, 'UTF-8'));
    }

    public function test_the_app_icon_still_comes_out_as_a_png(): void
    {
        $response = $this->get(route('app.icon', 192))->assertOk();
        $this->assertStringStartsWith("\x89PNG", $response->getContent());
        $this->assertStringStartsWith("\x89PNG", $this->get(route('app.icon', 192))->getContent());
    }
}
