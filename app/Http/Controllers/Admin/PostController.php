<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BlogController;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\Content\Images;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

/**
 * Writing, scheduling and publishing blog articles.
 */
class PostController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $show = in_array($request->query('show'), ['published', 'scheduled', 'draft'], true) ? $request->query('show') : 'all';
        $search = trim((string) $request->query('q'));

        $posts = Post::query()
            ->when($show === 'published', fn ($q) => $q->where('published_at', '<=', now()))
            ->when($show === 'scheduled', fn ($q) => $q->where('published_at', '>', now()))
            ->when($show === 'draft', fn ($q) => $q->whereNull('published_at'))
            ->when($search !== '', fn ($q) => $q->where('title', 'like', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%'))
            ->orderByRaw('published_at is null desc')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.posts.index', [
            'posts' => $posts,
            'show' => $show,
            'search' => $search,
            'counts' => [
                'published' => Post::where('published_at', '<=', now())->count(),
                'scheduled' => Post::where('published_at', '>', now())->count(),
                'draft' => Post::whereNull('published_at')->count(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.posts.form', $this->formData(new Post([
            'swatch' => 'navy',
            'topic' => Post::topics()[0] ?? 'Faith',
            'author_name' => $request->user()->name,
        ])));
    }

    public function store(Request $request, Images $images): RedirectResponse
    {
        $post = new Post(['user_id' => $request->user()->id]);
        $this->fill($request, $post, $images);

        return $this->saved($request, $post, 'added');
    }

    public function edit(Post $post): View
    {
        return view('admin.posts.form', $this->formData($post));
    }

    public function update(Request $request, Post $post, Images $images): RedirectResponse
    {
        $this->fill($request, $post, $images);

        return $this->saved($request, $post, 'saved');
    }

    public function destroy(Post $post, Images $images): RedirectResponse
    {
        $images->delete($post->cover_path, $post->cover_thumb_path);
        $post->delete();

        return redirect()->route('admin.posts.index')->with('status', "\"{$post->title}\" deleted.");
    }

    /**
     * How the article will look on the website, even before it is published.
     */
    public function preview(Post $post, BlogController $blog): View
    {
        return $blog->article($post, preview: true);
    }

    /**
     * A picture for inside an article. Returns the Markdown to insert.
     */
    public function upload(Request $request, Images $images): JsonResponse
    {
        $request->validate(['image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:15360']]);

        try {
            $saved = $images->store($request->file('image'), 'blog/inline', 1600, 640);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        $alt = str_replace(['[', ']'], '', pathinfo($request->file('image')->getClientOriginalName(), PATHINFO_FILENAME));

        // A path without the domain keeps working if the site's address changes.
        $url = Storage::disk('public')->url($saved['path']);
        $url = Str::startsWith($url, url('/')) ? Str::after($url, url('/')) : $url;

        return response()->json(['markdown' => '!['.$alt.']('.$url.')']);
    }

    private function fill(Request $request, Post $post, Images $images): void
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'topic' => ['required', 'string', 'max:60'],
            'dek' => ['nullable', 'string', 'max:300'],
            'body' => ['nullable', 'string', 'max:100000'],
            'cover' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:15360'],
            'cover_alt' => ['nullable', 'string', 'max:200'],
            'remove_cover' => ['nullable', 'boolean'],
            'swatch' => ['required', Rule::in(Post::SWATCHES)],
            'author_name' => ['nullable', 'string', 'max:100'],
            'author_bio' => ['nullable', 'string', 'max:300'],
            'meta_description' => ['nullable', 'string', 'max:300'],
            'status' => ['required', Rule::in(['draft', 'published', 'scheduled'])],
            'publish_at' => ['nullable', 'required_if:status,scheduled', 'date'],
        ], [
            'slug.regex' => 'Use lowercase letters, numbers and dashes only, e.g. dating-with-purpose.',
            'publish_at.required_if' => 'Choose when this article should go live.',
        ]);

        $slug = Post::uniqueSlug(($data['slug'] ?? null) ?: $data['title'], $post->id);
        if (($data['slug'] ?? null) && $slug !== $data['slug']) {
            throw ValidationException::withMessages(['slug' => 'Another article already uses this address.']);
        }

        $post->fill([
            'title' => $data['title'],
            'slug' => $slug,
            'topic' => $data['topic'],
            'dek' => $data['dek'] ?? null,
            'body' => $data['body'] ?? null,
            'cover_alt' => $data['cover_alt'] ?? null,
            'swatch' => $data['swatch'],
            'author_name' => $data['author_name'] ?? null,
            'author_bio' => $data['author_bio'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'is_featured' => $request->boolean('is_featured'),
            'published_at' => match ($data['status']) {
                'draft' => null,
                'scheduled' => Carbon::parse($data['publish_at']),
                // Keep the original date when re-saving a published article.
                'published' => $post->isLive() ? $post->published_at : now(),
            },
        ]);

        if ($request->boolean('remove_cover') || $request->hasFile('cover')) {
            $images->delete($post->cover_path, $post->cover_thumb_path);
            $post->cover_path = $post->cover_thumb_path = null;
        }
        if ($request->hasFile('cover')) {
            try {
                $saved = $images->store($request->file('cover'), 'blog', 2000, 720);
            } catch (RuntimeException $e) {
                throw ValidationException::withMessages(['cover' => $e->getMessage()]);
            }
            $post->cover_path = $saved['path'];
            $post->cover_thumb_path = $saved['thumb_path'];
        }

        DB::transaction(function () use ($post) {
            $post->save();
            // One featured article at a time.
            if ($post->is_featured) {
                Post::whereKeyNot($post->id)->where('is_featured', true)->update(['is_featured' => false]);
            }
        });
    }

    private function saved(Request $request, Post $post, string $verb): RedirectResponse
    {
        $message = match ($post->status()) {
            'draft' => "Draft \"{$post->title}\" {$verb}.",
            'scheduled' => "\"{$post->title}\" will go live on {$post->published_at->format('j M Y, g:i A')}.",
            default => "\"{$post->title}\" is live.",
        };

        // "Save and preview" keeps the editor open with the preview in a new tab.
        if ($request->input('then') === 'preview') {
            return redirect()->route('admin.posts.edit', $post)->with('status', $message)->with('preview', route('admin.posts.preview', $post));
        }

        return redirect()->route('admin.posts.edit', $post)->with('status', $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Post $post): array
    {
        return [
            'post' => $post,
            'topics' => Post::topics(),
            'swatches' => Post::SWATCHES,
        ];
    }
}
