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

#[Fillable(['slug', 'name', 'description', 'price', 'stock', 'image_path', 'is_active'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

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
        return '₦'.number_format($this->price / 100);
    }
}
