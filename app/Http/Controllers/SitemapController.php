<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\GalleryAlbum;
use App\Models\Post;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Every public page, for search engines.
     */
    public function __invoke(): Response
    {
        $urls = collect(['home', 'about', 'watch', 'blog.index', 'events', 'gallery.index', 'give', 'market.index', 'notices.index', 'contact'])
            ->map(fn ($name) => ['loc' => route($name), 'lastmod' => null]);

        $urls = $urls
            ->concat(Post::published()->get(['slug', 'updated_at'])->map(fn ($p) => ['loc' => $p->url(), 'lastmod' => $p->updated_at]))
            ->concat(GalleryAlbum::published()->get(['slug', 'updated_at'])->map(fn ($a) => ['loc' => route('gallery.show', $a), 'lastmod' => $a->updated_at]))
            ->concat(Event::upcoming()->get()->map(fn ($e) => ['loc' => route('events.show', $e), 'lastmod' => $e->updated_at]));

        return response()->view('sitemap', ['urls' => $urls], 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
