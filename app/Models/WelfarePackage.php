<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A welfare package the welfare unit gives out (e.g. a food package before Christmas).
 * Members apply while it's open; the unit approves, waitlists or declines, then marks collection.
 */
#[Fillable(['unit_id', 'title', 'description', 'items', 'slots', 'opens_at', 'closes_at', 'collection_details', 'is_published', 'created_by'])]
class WelfarePackage extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'is_published' => 'boolean',
            'slots' => 'integer',
        ];
    }

    /**
     * @return HasMany<WelfarePackageApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(WelfarePackageApplication::class);
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    #[Scope]
    protected function open(Builder $query): void
    {
        $query->where('is_published', true)->where('opens_at', '<=', now())->where('closes_at', '>', now())->orderBy('closes_at');
    }

    public function isOpen(): bool
    {
        return $this->is_published && $this->opens_at->lte(now()) && $this->closes_at->gt(now());
    }

    /**
     * Places given out so far (approved or collected).
     */
    public function placesTaken(): int
    {
        return $this->applications()->whereIn('status', ['approved', 'collected'])->count();
    }

    public function placesLeft(): ?int
    {
        return $this->slots === null ? null : max(0, $this->slots - $this->placesTaken());
    }
}
