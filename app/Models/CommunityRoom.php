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
 * A chat room in the community. A room tied to a unit is only for that unit's members.
 */
#[Fillable(['name', 'slug', 'emoji', 'description', 'unit_id', 'is_locked', 'position'])]
class CommunityRoom extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_locked' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return HasMany<CommunityMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(CommunityMessage::class);
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Rooms this person can see: everyone's rooms, plus their own unit's.
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $query->where(function ($q) use ($user) {
            $q->whereNull('unit_id');
            if ($user->moderatesCommunity()) {
                $q->orWhereNotNull('unit_id');
            } elseif ($user->member?->unit_id) {
                $q->orWhere('unit_id', $user->member->unit_id);
            }
        })->orderBy('position')->orderBy('id');
    }

    public function isVisibleTo(User $user): bool
    {
        return $this->unit_id === null || $user->moderatesCommunity() || $user->member?->unit_id === $this->unit_id;
    }

    public function canPost(User $user): bool
    {
        return $this->isVisibleTo($user) && ! $user->isMutedInCommunity() && (! $this->is_locked || $user->moderatesCommunity());
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::limit(Str::slug($name), 60, '') ?: 'room';
        if (in_array($base, ['quiz', 'messages'], true)) {
            $base .= '-room'; // these addresses are taken by the community's own pages
        }
        $slug = $base;
        for ($i = 2; static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
