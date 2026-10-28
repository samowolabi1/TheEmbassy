<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class BlogController extends Controller
{
    /**
     * Articles, newest first, with a topic filter and search.
     */
    public function index(Request $request): View
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100'], 'topic' => ['nullable', 'string', 'max:60']]);
        $search = trim((string) $request->query('q'));
        $topic = (string) $request->query('topic');
        $filtered = $search !== '' || $topic !== '';

        // The featured article leads the first page when nothing is filtered.
        $featured = $filtered || $request->integer('page', 1) > 1 ? null
            : (Post::published()->where('is_featured', true)->first() ?? Post::published()->first());

        $posts = Post::published()
            ->when($featured, fn ($q) => $q->whereKeyNot($featured->id))
            ->when($topic !== '', fn ($q) => $q->where('topic', $topic))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
                $q->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('dek', 'like', $like)->orWhere('body', 'like', $like));
            })
            ->paginate(9)
            ->withQueryString();

        return view('blog.index', [
            'featured' => $featured,
            'posts' => $posts,
            'topics' => Post::topics(),
            'topic' => $topic,
            'search' => $search,
        ]);
    }

    public function show(string $slug): View|RedirectResponse
    {
        $post = Post::firstWhere('slug', $slug);
        if (! $post) {
            $moved = Post::findByPreviousSlug($slug);
            abort_unless($moved?->isLive(), 404);

            return redirect()->to($moved->url(), 301);
        }
        abort_unless($post->isLive(), 404);

        return $this->article($post);
    }

    /**
     * The article page; staff also use it to preview drafts.
     */
    public function article(Post $post, bool $preview = false): View
    {
        $related = Post::published()->whereKeyNot($post->id)
            ->reorder()->orderByRaw('topic = ? desc', [$post->topic])->orderByDesc('published_at')
            ->take(3)->get();

        return view('blog.show', ['post' => $post, 'related' => $related, 'preview' => $preview]);
    }

    /**
     * RSS feed of the latest articles.
     */
    public function feed(): Response
    {
        $posts = Post::published()->take(20)->get();

        return response()->view('blog.feed', ['posts' => $posts], 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
    }
}
