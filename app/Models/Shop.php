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
use Illuminate\Support\Facades\Storage;

/**
 * A member's business on the marketplace. Buyers pay the seller directly; the seller pays a
 * subscription to be listed.
 */
#[Fillable(['slug', 'name', 'owner_name', 'email', 'phone', 'whatsapp', 'category', 'description', 'cac_number',
    'tagline', 'about', 'accent', 'announcement', 'hours', 'address', 'delivery_info', 'returns_info', 'instagram', 'facebook', 'tiktok', 'website'])]
class Shop extends Model
{
    /** @use HasFactory<ShopFactory> */
    use HasFactory;

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const DECLINED = 'declined';

    public const SUSPENDED = 'suspended';

    /** Colours a seller can give their shop: name => [main, soft background]. */
    public const ACCENTS = [
        'navy' => ['Royal navy', '#0a1d3f', '#e6eef7'],
        'gold' => ['Crown gold', '#9a6b00', '#fff1c7'],
        'emerald' => ['Emerald', '#0f6b4a', '#e1f4ec'],
        'teal' => ['Teal', '#0b6470', '#ddf2f4'],
        'plum' => ['Plum', '#6b2a6b', '#f4e6f4'],
        'rose' => ['Rose', '#b0284f', '#fde6ee'],
        'coral' => ['Coral', '#b5482a', '#fde9e2'],
        'charcoal' => ['Charcoal', '#2b2f36', '#eceef1'],
    ];

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
            'final_reminded_for' => 'datetime',
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

    /**
     * The sections products are arranged in, in the seller's order.
     *
     * @return HasMany<ShopCollection, $this>
     */
    public function collections(): HasMany
    {
        return $this->hasMany(ShopCollection::class)->orderBy('position')->orderBy('name');
    }

    public function bannerUrl(): ?string
    {
        return $this->banner_path ? Storage::disk('public')->url($this->banner_path) : null;
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    /**
     * @return array{0: string, 1: string} the shop's colour and its soft background
     */
    public function colours(): array
    {
        $a = self::ACCENTS[$this->accent] ?? self::ACCENTS['navy'];

        return [$a[1], $a[2]];
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
    }

    /**
     * Social links as label => URL, from handles or full links.
     *
     * @return array<string, string>
     */
    public function socialLinks(): array
    {
        $handle = fn (?string $v, string $base) => blank($v) ? null : (str_starts_with($v, 'http') ? $v : $base.ltrim(trim($v), '@'));

        return array_filter([
            'Instagram' => $handle($this->instagram, 'https://instagram.com/'),
            'Facebook' => $handle($this->facebook, 'https://facebook.com/'),
            'TikTok' => $handle($this->tiktok, 'https://www.tiktok.com/@'),
            'Website' => blank($this->website) ? null : (str_starts_with($this->website, 'http') ? $this->website : 'https://'.$this->website),
        ]);
    }

    public function mapUrl(): ?string
    {
        return $this->address ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($this->address) : null;
    }
}
