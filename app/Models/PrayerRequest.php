<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A prayer request: from the home page form (anyone), or from a member's account, where they
 * follow it up, hear back from the prayer team, and can share it on the members' prayer wall.
 */
#[Fillable(['name', 'contact', 'request', 'is_private', 'category', 'on_wall', 'anonymous'])]
class PrayerRequest extends Model
{
    public const CATEGORIES = [
        'health' => 'Health and healing',
        'family' => 'Family and relationships',
        'finance' => 'Finances and provision',
        'work' => 'Work, school or business',
        'spiritual' => 'Spiritual growth',
        'guidance' => 'Direction and decisions',
        'grief' => 'Grief and loss',
        'thanks' => 'Thanksgiving',
        'other' => 'Something else',
    ];

    /** Who can see a member's request. */
    public const AUDIENCES = [
        'pastors' => 'Only the pastors (private)',
        'team' => 'The pastors and prayer team',
        'church' => 'The church too, on the members\' prayer wall',
    ];

    /** How long a request stays on the prayer wall. */
    public const WALL_DAYS = 60;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_private' => 'boolean',
            'on_wall' => 'boolean',
            'anonymous' => 'boolean',
            'read_at' => 'datetime',
            'answered_at' => 'datetime',
        ];
    }

    /**
     * Requests nobody on the team has marked as read yet.
     */
    #[Scope]
    protected function unread(Builder $query): void
    {
        $query->whereNull('read_at');
    }

    /**
     * On the prayer wall: shared by a member, and recent.
     */
    #[Scope]
    protected function wall(Builder $query): void
    {
        $query->where('on_wall', true)->whereNotNull('member_id')->where('created_at', '>=', now()->subDays(self::WALL_DAYS));
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @return HasMany<PrayerRequestReply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(PrayerRequestReply::class)->oldest('id');
    }

    public function audience(): string
    {
        return $this->is_private ? 'pastors' : ($this->on_wall ? 'church' : 'team');
    }

    /**
     * The name on the prayer wall: first name only, or none.
     */
    public function wallName(): string
    {
        return $this->anonymous || ! $this->member ? 'A member of '.config('church.short_name') : Member::firstNameOf($this->member->name);
    }

    public function categoryLabel(): ?string
    {
        return self::CATEGORIES[$this->category] ?? null;
    }
}
