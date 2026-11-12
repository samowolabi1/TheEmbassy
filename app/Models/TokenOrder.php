<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Something bought in the market with tokens. The tokens are held from the buyer until the
 * vendor hands it over (then they go to the vendor), or go back to the buyer if it's cancelled.
 */
#[Fillable(['product_id', 'shop_id', 'user_id', 'product_name', 'tokens', 'note', 'status', 'cancelled_by', 'completed_at', 'cancelled_at'])]
class TokenOrder extends Model
{
    public const PENDING = 'pending';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    /** Orders the vendor hasn't handed over by then are cancelled and refunded. */
    public const EXPIRES_AFTER_DAYS = 14;

    protected static function booted(): void
    {
        static::creating(function (TokenOrder $order) {
            do {
                $order->code = 'TK-'.Str::upper(Str::random(6));
            } while (static::where('code', $order->code)->exists());
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::PENDING => 'Waiting for the seller',
            self::COMPLETED => 'Received',
            default => match ($this->cancelled_by) {
                'vendor' => 'Declined by the seller · tokens returned',
                'expired' => 'Expired · tokens returned',
                default => 'Cancelled · tokens returned',
            },
        };
    }
}
