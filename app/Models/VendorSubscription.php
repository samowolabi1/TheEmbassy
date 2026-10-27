<?php

namespace App\Models;

use App\Contracts\Payable;
use App\Mail\SubscriptionActivated;
use App\Models\Concerns\HasPayments;
use Database\Factories\VendorSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * A seller's paid period on the market. Their listings show while one is active.
 */
#[Fillable(['vendor_plan_id', 'coupon_id', 'months', 'list_price', 'discount', 'amount'])]
class VendorSubscription extends Model implements Payable
{
    /** @use HasFactory<VendorSubscriptionFactory> */
    use HasFactory, HasPayments;

    public const AWAITING_PAYMENT = 'awaiting_payment';

    public const ACTIVE = 'active';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'months' => 'integer',
            'list_price' => 'integer',
            'discount' => 'integer',
            'amount' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * @return BelongsTo<VendorPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(VendorPlan::class, 'vendor_plan_id');
    }

    /**
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * Start the paid period. Renewing early adds on after the current one, so no days are lost.
     */
    public function activate(): void
    {
        if ($this->status === self::ACTIVE) {
            return;
        }

        $current = $this->shop->subscriptions()->where('status', self::ACTIVE)->max('ends_at');
        $starts = $current && now()->lt($current) ? Carbon::parse($current) : now();

        $this->forceFill([
            'status' => self::ACTIVE,
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addMonthsNoOverflow($this->months),
        ])->save();

        DB::afterCommit(function () {
            try {
                Mail::to($this->shop->email)->send(new SubscriptionActivated($this));
            } catch (Throwable $e) {
                Log::error('Could not email subscription receipt', ['subscription' => $this->id, 'error' => $e->getMessage()]);
            }
        });
    }

    public function feeModule(): string
    {
        return 'market';
    }

    public function paymentSucceeded(Payment $payment): void
    {
        $this->activate();
    }

    public function paymentReturnUrl(Payment $payment): string
    {
        return route('vendor.subscription.index', ['paid' => $this->id]);
    }
}
