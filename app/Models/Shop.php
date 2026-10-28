<?php

namespace App\Models;

use Database\Factories\ShopFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A member's business on the marketplace. Buyers pay the seller directly; the seller pays a
 * subscription to be listed.
 */
#[Fillable(['slug', 'name', 'owner_name', 'email', 'phone', 'whatsapp', 'category', 'description', 'cac_number'])]
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
            'reminded_for' => 'datetime',
            'lapse_noticed_for' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * The seller's login.
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
     * @return HasMany<VendorSubscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(VendorSubscription::class);
    }

    /**
     * @return HasMany<Enquiry, $this>
     */
    public function enquiries(): HasMany
    {
        return $this->hasMany(Enquiry::class);
    }

    #[Scope]
    protected function approved(Builder $query): void
    {
        $query->where('status', self::APPROVED);
    }

    /**
     * Approved and with a paid-up subscription: these shops appear in the market.
     */
    #[Scope]
    protected function listed(Builder $query): void
    {
        $query->where('status', self::APPROVED)
            ->whereHas('subscriptions', fn ($q) => $q->where('status', VendorSubscription::ACTIVE)
                ->where('starts_at', '<=', now())->where('ends_at', '>', now()));
    }

    public function isApproved(): bool
    {
        return $this->status === self::APPROVED;
    }

    /**
     * When the shop's paid time runs out (including renewals already paid for), or null.
     */
    public function subscribedUntil(): ?Carbon
    {
        $end = $this->subscriptions()->where('status', VendorSubscription::ACTIVE)->max('ends_at');

        return $end ? Carbon::parse($end) : null;
    }

    public function isSubscribed(): bool
    {
        return $this->subscriptions()->where('status', VendorSubscription::ACTIVE)
            ->where('starts_at', '<=', now())->where('ends_at', '>', now())->exists();
    }

    public function isListed(): bool
    {
        return $this->isApproved() && $this->isSubscribed();
    }

    /**
     * A wa.me link for the shop's WhatsApp (or phone) number, in international format.
     */
    public function whatsappUrl(): ?string
    {
        $digits = preg_replace('/\D/', '', (string) ($this->whatsapp ?: $this->phone));
        if ($digits === '') {
            return null;
        }
        // Nigerian numbers written locally (0803…) become 234803…
        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $digits = '234'.substr($digits, 1);
        }

        return 'https://wa.me/'.$digits;
    }

    public function telUrl(): string
    {
        return 'tel:'.preg_replace('/[^\d+]/', '', $this->phone);
    }
}
