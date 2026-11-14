<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A book in the church library. It may have several copies; members borrow them one at a time.
 */
#[Fillable(['title', 'author', 'category', 'description', 'isbn', 'copies', 'shelf', 'is_active'])]
class Book extends Model
{
    public const CATEGORIES = [
        'christian-living' => 'Christian living',
        'devotional' => 'Devotionals',
        'bible-study' => 'Bible study and theology',
        'prayer' => 'Prayer',
        'biography' => 'Biographies',
        'relationships' => 'Marriage, family and relationships',
        'leadership' => 'Leadership and purpose',
        'money-career' => 'Money and career',
        'youth' => 'Youth and campus',
        'children' => 'Children',
        'fiction' => 'Christian fiction',
    ];

    /** Colours for books without a cover photo. */
    public const SPINES = ['#0a1d3f', '#7c3aed', '#16a34a', '#b45309', '#9d174d', '#0e7490', '#1e3a8a', '#92400e'];

    protected static function booted(): void
    {
        static::creating(function (Book $book) {
            if (! $book->slug) {
                $base = Str::slug($book->title) ?: 'book';
                $slug = $base;
                for ($n = 2; static::where('slug', $slug)->exists(); $n++) {
                    $slug = "{$base}-{$n}";
                }
                $book->slug = $slug;
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
        return ['is_active' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return HasMany<BookLoan, $this>
     */
    public function loans(): HasMany
    {
        return $this->hasMany(BookLoan::class);
    }

    /**
     * @return HasMany<BookReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(BookReview::class);
    }

    /**
     * @param  Builder<Book>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Copies out or set aside for someone.
     */
    public function copiesTaken(): int
    {
        return $this->loans()->whereIn('status', [BookLoan::REQUESTED, BookLoan::ON_LOAN])->count();
    }

    public function copiesFree(): int
    {
        return max(0, $this->copies - $this->copiesTaken());
    }

    public function queueLength(): int
    {
        return $this->loans()->where('status', BookLoan::WAITING)->count();
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? Str::headline($this->category);
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }

    public function thumbUrl(): ?string
    {
        return $this->cover_thumb_path ? Storage::disk('public')->url($this->cover_thumb_path) : $this->coverUrl();
    }

    /**
     * A steady colour for the drawn cover, from the title.
     */
    public function spine(): string
    {
        return self::SPINES[crc32($this->title) % count(self::SPINES)];
    }
}
