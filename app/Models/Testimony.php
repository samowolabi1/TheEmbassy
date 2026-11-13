<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * What God has done, shared by a member. It's published once a pastor or the office has read it,
 * on the website if the member agreed, with their name shown the way they chose.
 */
#[Fillable(['title', 'body', 'category', 'name_display', 'share_publicly', 'share_in_church'])]
class Testimony extends Model
{
    public const CATEGORIES = [
        'healing' => 'Healing',
        'provision' => 'Provision',
        'breakthrough' => 'Breakthrough',
        'salvation' => 'Salvation',
        'deliverance' => 'Deliverance',
        'family' => 'Family and marriage',
        'career' => 'Career, school or business',
        'protection' => 'Protection',
        'other' => 'Other',
    ];

    public const NAME_DISPLAYS = [
        'full' => 'My full name',
        'first' => 'My first name only',
        'anonymous' => 'No name, just "a member of the church"',
    ];

    public const STATUSES = [
        'pending' => 'Being read by the pastors',
        'published' => 'Published',
        'declined' => 'Not published',
        'withdrawn' => 'Withdrawn',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'share_publicly' => 'boolean',
            'share_in_church' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Published, and the member agreed to it being on the website.
     *
     * @param  Builder<Testimony>  $query
     */
    #[Scope]
    protected function public(Builder $query): void
    {
        $query->where('status', 'published')->where('share_publicly', true);
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @return HasMany<TestimonyPraise, $this>
     */
    public function praiseList(): HasMany
    {
        return $this->hasMany(TestimonyPraise::class);
    }

    /**
     * The name as the member chose to be shown.
     */
    public function byline(): string
    {
        $anonymous = 'A member of '.config('church.short_name');

        return match ($this->name_display) {
            'anonymous' => $anonymous,
            'first' => Member::firstNameOf($this->member?->name),
            default => (string) $this->member?->name,
        } ?: $anonymous;
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? Str::headline($this->category);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function excerpt(int $length = 220): string
    {
        return Str::limit(preg_replace('/\s+/', ' ', $this->body), $length);
    }
}
