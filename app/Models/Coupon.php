<?php

namespace App\Models;

use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A discount code for seller subscriptions: 10%, 20%, 50% or 100% off.
 */
#[Fillable(['code', 'percent_off', 'max_uses', 'expires_at', 'is_active'])]
class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'percent_off' => 'integer',
            'max_uses' => 'integer',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Codes are stored in capitals so buyers can type them in any case.
     */
    public static function findByCode(?string $code): ?self
    {
        $code = Str::upper(trim((string) $code));

        return $code === '' ? null : static::firstWhere('code', $code);
    }

    /**
     * @return HasMany<VendorSubscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(VendorSubscription::class);
    }

    /**
     * Times it has been used on an active subscription.
     */
    public function uses(): int
    {
        return $this->subscriptions()->where('status', VendorSubscription::ACTIVE)->count();
    }

    /**
     * Why it can't be used right now, or null if it can.
     */
    public function problem(): ?string
    {
        return match (true) {
            ! $this->is_active => 'That coupon has been switched off.',
            $this->expires_at?->isPast() => 'That coupon has expired.',
            $this->max_uses !== null && $this->uses() >= $this->max_uses => 'That coupon has been used up.',
            default => null,
        };
    }

    /**
     * The discount on a price, in kobo.
     */
    public function discountOn(int $price): int
    {
        return (int) round($price * $this->percent_off / 100);
    }
}
