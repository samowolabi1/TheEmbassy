<?php

namespace App\Models;

use Database\Factories\ShopFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A member's business on the marketplace.
 */
#[Fillable([
    'slug', 'name', 'owner_name', 'email', 'phone', 'category', 'description', 'cac_number',
    'bank_name', 'bank_code', 'account_number',
])]
#[Hidden(['account_number', 'subaccount_code'])]
class Shop extends Model
{
    /** @use HasFactory<ShopFactory> */
    use HasFactory;

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const DECLINED = 'declined';

    public const SUSPENDED = 'suspended';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * The vendor's login.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<MarketOrder, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(MarketOrder::class);
    }

    #[Scope]
    protected function approved(Builder $query): void
    {
        $query->where('status', self::APPROVED);
    }

    public function isApproved(): bool
    {
        return $this->status === self::APPROVED;
    }

    /**
     * Approved and able to receive its share of each payment.
     */
    public function canSell(): bool
    {
        return $this->isApproved() && filled($this->subaccount_code);
    }

    /**
     * The last four digits only, for showing in the admin.
     */
    public function maskedAccount(): string
    {
        return '••••'.substr($this->account_number, -4);
    }
}
