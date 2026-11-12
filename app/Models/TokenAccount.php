<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Somewhere tokens are held: a member's wallet, the church treasury (where tokens are minted and
 * come back to), or escrow (tokens held for market orders until the seller hands the item over).
 */
#[Fillable(['kind', 'user_id', 'balance', 'frozen_at', 'frozen_reason'])]
class TokenAccount extends Model
{
    public const TREASURY = 'treasury';

    public const ESCROW = 'escrow';

    public const MEMBER = 'member';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['balance' => 'integer', 'frozen_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    #[Scope]
    protected function members(Builder $query): void
    {
        $query->where('kind', self::MEMBER);
    }

    public function isFrozen(): bool
    {
        return $this->frozen_at !== null;
    }

    public function label(): string
    {
        return match ($this->kind) {
            self::TREASURY => 'Church treasury',
            self::ESCROW => 'Held for orders',
            default => $this->user?->communityName() ?? 'Former member',
        };
    }
}
