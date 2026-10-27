<?php

namespace App\Models;

use App\Contracts\Payable;
use App\Models\Concerns\HasPayments;
use Database\Factories\NoticeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * A paid listing on the notice board from another church or a business.
 */
#[Fillable([
    'advertiser_type', 'organisation', 'contact_name', 'email', 'phone',
    'category', 'title', 'body', 'url', 'image_path', 'package', 'days', 'featured', 'amount',
])]
class Notice extends Model implements Payable
{
    /** @use HasFactory<NoticeFactory> */
    use HasFactory, HasPayments;

    public const AWAITING_PAYMENT = 'awaiting_payment';

    public const IN_REVIEW = 'in_review';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'featured' => 'boolean',
            'amount' => 'integer',
            'days' => 'integer',
            'paid_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * Approved and within its paid run. Featured first, then newest.
     */
    #[Scope]
    protected function live(Builder $query): void
    {
        $query->where('status', self::APPROVED)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->orderByDesc('featured')
            ->latest('starts_at');
    }

    /**
     * What the admin list and status page show: awaiting payment, in review, live, expired or rejected.
     */
    public function displayStatus(): string
    {
        if ($this->status === self::APPROVED) {
            return $this->ends_at?->isPast() ? 'expired' : 'live';
        }

        return $this->status;
    }

    /**
     * Publish it now for the days that were paid for.
     */
    public function approve(): void
    {
        $this->forceFill([
            'status' => self::APPROVED,
            'rejection_reason' => null,
            'starts_at' => now(),
            'ends_at' => now()->addDays($this->days),
        ])->save();
    }

    public function reject(string $reason): void
    {
        $this->forceFill(['status' => self::REJECTED, 'rejection_reason' => $reason])->save();
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    public function feeModule(): string
    {
        return 'notices';
    }

    public function paymentSucceeded(Payment $payment): void
    {
        $this->forceFill(['status' => self::IN_REVIEW, 'paid_at' => $payment->paid_at])->save();
    }

    public function paymentReturnUrl(Payment $payment): string
    {
        return $this->statusUrl();
    }

    /**
     * A private link the advertiser can use to check on their notice.
     */
    public function statusUrl(): string
    {
        return URL::signedRoute('notices.status', $this);
    }
}
