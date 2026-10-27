<?php

namespace App\Http\Controllers;

use Illuminate\Support\Collection;
use Illuminate\View\View;

class BlogController extends Controller
{
    /**
     * The blog: a featured article and the rest, filterable by topic.
     */
    public function index(): View
    {
        $posts = $this->posts();
        $featured = $posts->firstWhere('featured', true) ?? $posts->first();

        return view('blog.index', [
            'featured' => $featured,
            'posts' => $posts->reject(fn ($p) => $p['slug'] === $featured['slug']),
            'topics' => config('content.posts.topics'),
        ]);
    }

    /**
     * One article.
     */
    public function show(string $slug): View
    {
        $posts = $this->posts();
        $post = $posts->firstWhere('slug', $slug) ?? abort(404);

        return view('blog.show', [
            'post' => $post,
            'related' => $posts->reject(fn ($p) => $p['slug'] === $slug)->take(3),
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function posts(): Collection
    {
        return collect(config('content.posts.posts'));
    }
}
