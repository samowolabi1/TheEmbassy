<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Declaration;
use App\Models\Event;
use App\Models\Notice;
use App\Models\Product;
use App\Services\Content\SermonLibrary;
use Illuminate\View\View;

class PageController extends Controller
{
    /**
     * The home page.
     */
    public function home(SermonLibrary $library): View
    {
        $messages = $library->messages();
        $headline = $library->headline();

        return view('pages.home', [
            'home' => config('content.home'),
            'declaration' => Declaration::current(),
            'announcements' => Announcement::live()->take(3)->get(),
            'notices' => Notice::live()->where('featured', true)->take(3)->get(),
            'products' => Product::listed()->with('shop')->take(4)->get(),
            'headline' => $headline,
            // The latest messages after the headline one, with a podcast if there is one.
            'sideMessages' => $messages->reject(fn ($m) => $m['title'] === $headline['title'])->take(2)
                ->concat($messages->where('type', 'podcast')->take(1))->unique('title')->take(3)->values(),
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
    public function watch(SermonLibrary $library): View
    {
        return view('pages.watch', [
            'filters' => config('content.sermons.filters'),
            'messages' => $library->messages(),
            'series' => $library->series(),
            'upcoming' => $library->upcomingLive(),
            'headline' => $library->headline(),
        ]);
    }
}
