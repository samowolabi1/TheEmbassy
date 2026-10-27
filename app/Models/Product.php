<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Something sold on the market: a product (goods, optionally with stock) or a service (booked, no stock).
 */
#[Fillable(['type', 'slug', 'name', 'description', 'price', 'price_unit', 'stock', 'image_path', 'is_active'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    public const PRODUCT = 'product';

    public const SERVICE = 'service';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Shown in the market: active, from an approved shop, newest first.
     */
    #[Scope]
    protected function listed(Builder $query): void
    {
        $query->where('is_active', true)
            ->whereHas('shop', fn ($q) => $q->where('status', Shop::APPROVED))
            ->latest();
    }

    public function isService(): bool
    {
        return $this->type === self::SERVICE;
    }

    /**
     * What a quantity counts for a service priced "per hour" etc. ("hours"), or null when it's booked once.
     */
    public function unitNoun(): ?string
    {
        if (! $this->isService() || ! $this->price_unit || ! preg_match('/^per\s+(.+)$/i', trim($this->price_unit), $m)) {
            return null;
        }

        return Str::plural(strtolower($m[1]));
    }

    public function inStock(): bool
    {
        return $this->stock === null || $this->stock > 0;
    }

    /**
     * Can be bought online right now.
     */
    public function canBuy(): bool
    {
        return $this->is_active && $this->inStock() && $this->shop->canSell();
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    public function priceLabel(): string
    {
        $price = '₦'.number_format($this->price / 100);

        return $this->isService() && $this->price_unit ? "{$price} {$this->price_unit}" : $price;
    }
}
