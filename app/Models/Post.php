<?php

namespace App\Models;

use App\Services\Content\Markdown;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A blog article. Drafts have no publish date; a future date schedules it.
 */
#[Fillable(['title', 'slug', 'topic', 'dek', 'body', 'cover_path', 'cover_thumb_path', 'cover_alt', 'swatch', 'user_id', 'author_name', 'author_bio', 'is_featured', 'meta_description', 'published_at'])]
class Post extends Model
{
    use HasFactory;

    public const SWATCHES = ['navy', 'gold', 'mid', 'ice', 'wash'];

    protected static function booted(): void
    {
        static::saving(function (Post $post) {
            if ($post->isDirty('body') || $post->body_html === null) {
                $post->body_html = app(Markdown::class)->toHtml($post->body);
                $post->reading_minutes = Markdown::readingMinutes($post->body);
            }

            // Keep old addresses working when the slug changes.
            if ($post->exists && $post->isDirty('slug') && $post->getOriginal('slug')) {
                $post->previous_slugs = collect($post->previous_slugs ?? [])->push($post->getOriginal('slug'))
                    ->reject(fn ($s) => $s === $post->slug)->unique()->values()->all();
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'previous_slugs' => 'array',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    #[Scope]
    protected function published(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now())->orderByDesc('published_at')->orderByDesc('id');
    }

    /**
     * Topics from config/content/posts.php, plus any already used on articles.
     *
     * @return list<string>
     */
    public static function topics(): array
    {
        return collect(config('content.posts.topics', []))->values()->all();
    }

    /**
     * A free address made from the title (or a given slug).
     */
    public static function uniqueSlug(string $text, ?int $ignoreId = null): string
    {
        $base = Str::limit(Str::slug($text), 80, '') ?: 'article';
        $slug = $base;
        for ($i = 2; static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    /**
     * The article an old address belonged to, if its slug was changed.
     */
    public static function findByPreviousSlug(string $slug): ?self
    {
        return static::where('previous_slugs', 'like', '%"'.$slug.'"%')->get()
            ->first(fn (Post $post) => in_array($slug, $post->previous_slugs ?? [], true));
    }

    public function status(): string
    {
        return match (true) {
            $this->published_at === null => 'draft',
            $this->published_at->isFuture() => 'scheduled',
            default => 'published',
        };
    }

    public function isLive(): bool
    {
        return $this->status() === 'published';
    }

    public function authorName(): string
    {
        return $this->author_name ?: ($this->user?->name ?? config('church.short_name'));
    }

    public function authorInitials(): string
    {
        return collect(preg_split('/\s+/', preg_replace('/[^\pL\s]/u', '', $this->authorName())))
            ->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('') ?: 'RT';
    }

    public function readingTime(): string
    {
        return $this->reading_minutes.' min read';
    }

    public function dateLabel(): string
    {
        return ($this->published_at ?? $this->updated_at ?? now())->format('j M Y');
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }

    public function thumbUrl(): ?string
    {
        return $this->cover_thumb_path ? Storage::disk('public')->url($this->cover_thumb_path) : $this->coverUrl();
    }

    public function summary(): string
    {
        return $this->meta_description ?: ($this->dek ?: Str::limit(trim(strip_tags((string) $this->body_html)), 160));
    }

    public function url(): string
    {
        return route('blog.show', $this->slug);
    }
}
