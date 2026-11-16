<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Something sold on the market: a product (goods, optionally with stock) or a service (booked, no stock).
 */
#[Fillable(['type', 'slug', 'name', 'description', 'price', 'price_unit', 'stock', 'image_path', 'is_active', 'token_price', 'shop_collection_id', 'compare_price', 'is_featured'])]
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
            'is_featured' => 'boolean',
            'compare_price' => 'integer',
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
     * Its photos, cover first.
     *
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return BelongsTo<ShopCollection, $this>
     */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(ShopCollection::class, 'shop_collection_id');
    }

    /**
     * Keep image_path (used for the cover in lists, share cards and old links) on the first photo.
     */
    public function syncCover(): void
    {
        $first = $this->images()->first();
        $this->forceFill(['image_path' => $first?->path])->save();
        $this->unsetRelation('images');
    }

    /**
     * A small copy of the cover for grids, or the cover itself.
     */
    public function thumbUrl(): ?string
    {
        $first = $this->relationLoaded('images') ? $this->images->first() : null;

        return $first ? $first->thumbUrl() : $this->imageUrl();
    }

    /**
     * On sale: a higher "was" price is set.
     */
    public function onSale(): bool
    {
        return $this->compare_price !== null && $this->compare_price > $this->price;
    }

    public function compareLabel(): ?string
    {
        return $this->onSale() ? '₦'.number_format($this->compare_price / 100) : null;
    }

    public function salePercent(): ?int
    {
        return $this->onSale() ? (int) round(100 - $this->price / $this->compare_price * 100) : null;
    }

    /**
     * Shown in the market: active, from an approved shop with a paid-up subscription, newest first.
     */
    #[Scope]
    protected function listed(Builder $query): void
    {
        $query->where('is_active', true)
            ->whereHas('shop', fn ($q) => $q->listed())
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
     * The seller lets members get this with tokens.
     */
    public function acceptsTokens(): bool
    {
        return $this->token_price !== null && $this->token_price > 0 && (bool) $this->shop?->user?->canUseTokens();
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
