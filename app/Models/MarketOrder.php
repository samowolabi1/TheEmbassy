<?php

namespace App\Models;

use App\Contracts\Payable;
use App\Contracts\SplitsPayment;
use App\Mail\MarketOrderPlaced;
use App\Models\Concerns\HasPayments;
use App\Services\Payments\Payments;
use Database\Factories\MarketOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

/**
 * A buyer's order of one product from one shop.
 */
#[Fillable([
    'shop_id', 'product_id', 'code', 'quantity', 'unit_price', 'total', 'commission', 'platform_fee',
    'buyer_name', 'buyer_email', 'buyer_phone', 'delivery_note',
])]
class MarketOrder extends Model implements Payable, SplitsPayment
{
    /** @use HasFactory<MarketOrderFactory> */
    use HasFactory, HasPayments;

    public const AWAITING_PAYMENT = 'awaiting_payment';

    public const PAID = 'paid';

    public const READY = 'ready';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    /** What the vendor can move a paid order to, and the label for each. */
    public const VENDOR_STEPS = [
        self::READY => 'Ready / on its way',
        self::COMPLETED => 'Delivered or collected',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'total' => 'integer',
            'commission' => 'integer',
            'platform_fee' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    /**
     * Build an order with the money split fixed at today's commission and platform fee.
     */
    public static function start(Product $product, int $quantity): self
    {
        $total = $product->price * $quantity;

        return new self([
            'shop_id' => $product->shop_id,
            'product_id' => $product->id,
            'code' => strtoupper(Str::random(8)),
            'quantity' => $quantity,
            'unit_price' => $product->price,
            'total' => $total,
            'commission' => (int) round($total * max(0, min(100, Setting::marketCommission())) / 100),
            'platform_fee' => Payments::platformFee('market', $total),
        ]);
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * What the vendor receives before Paystack's processing fee.
     */
    public function vendorShare(): int
    {
        return max(0, $this->total - $this->commission - $this->platform_fee);
    }

    public function isPaid(): bool
    {
        return ! in_array($this->status, [self::AWAITING_PAYMENT, self::CANCELLED], true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::AWAITING_PAYMENT => 'Awaiting payment',
            self::PAID => 'Paid: being prepared',
            self::READY => 'Ready / on its way',
            self::COMPLETED => 'Delivered',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function feeModule(): string
    {
        return 'market';
    }

    /**
     * The vendor's share goes to their subaccount and the church's commission to the church's;
     * whatever is left (the platform fee) stays with the main account. The vendor bears Paystack's fee.
     */
    public function paystackSplit(Payment $payment): array
    {
        $subaccounts = [['subaccount' => $this->shop->subaccount_code, 'share' => $this->vendorShare()]];
        $church = config('services.paystack.subaccount');
        if ($church && $this->commission > 0) {
            $subaccounts[] = ['subaccount' => $church, 'share' => $this->commission];
        }

        return [
            'type' => 'flat',
            'bearer_type' => 'subaccount',
            'bearer_subaccount' => $this->shop->subaccount_code,
            'subaccounts' => $subaccounts,
        ];
    }

    public function paymentSucceeded(Payment $payment): void
    {
        $this->forceFill(['status' => self::PAID, 'paid_at' => $payment->paid_at])->save();

        if ($this->product->stock !== null) {
            $this->product->forceFill(['stock' => max(0, $this->product->stock - $this->quantity)])->save();
        }

        DB::afterCommit(function () {
            try {
                Mail::to($this->buyer_email)->send(new MarketOrderPlaced($this, forVendor: false));
                Mail::to($this->shop->email)->send(new MarketOrderPlaced($this, forVendor: true));
            } catch (Throwable $e) {
                Log::error('Could not email market order', ['order' => $this->code, 'error' => $e->getMessage()]);
            }
        });
    }

    public function paymentReturnUrl(Payment $payment): string
    {
        return $this->url();
    }

    /**
     * The buyer's private link to their order.
     */
    public function url(): string
    {
        return URL::signedRoute('market.orders.show', $this);
    }
}
