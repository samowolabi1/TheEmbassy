<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Declaration;
use App\Models\Event;
use App\Models\Notice;
use App\Models\Product;
use Illuminate\View\View;

class PageController extends Controller
{
    /**
     * The home page.
     */
    public function home(): View
    {
        $messages = collect(config('content.sermons.messages'));
        $series = $messages->where('series', 'Crowned for More')->values();

        return view('pages.home', [
            'home' => config('content.home'),
            'declaration' => Declaration::current(),
            'announcements' => Announcement::live()->take(3)->get(),
            'notices' => Notice::live()->where('featured', true)->take(3)->get(),
            'products' => Product::listed()->with('shop')->take(4)->get(),
            'headline' => $series->first(),
            // The two earlier episodes, then the podcast.
            'sideMessages' => $series->slice(1)->concat($messages->where('type', 'podcast'))->take(3),
            'posts' => collect(config('content.posts.posts'))->reject(fn ($p) => $p['featured'] ?? false)->take(3),
            'topics' => config('content.posts.topics'),
            'events' => Event::upcoming()->take(3)->get(),
        ]);
    }

    /**
     * The About page.
     */
    public function about(): View
    {
        return view('pages.about', ['about' => config('content.about')]);
    }

    /**
     * The Watch page: livestream, series and the message library.
     */
    public function watch(): View
    {
        return view('pages.watch', ['sermons' => config('content.sermons')]);
    }

    /**
     * The Give page.
     */
    public function give(): View
    {
        return view('pages.give', [
            'presets' => [5000, 10000, 20000, 50000],
            'defaultAmount' => 10000,
            'funds' => [
                'tithe' => 'Tithe',
                'offering' => 'Offering',
                'building' => 'Building fund',
                'missions' => 'Missions & welfare',
            ],
        ]);
    }
}
