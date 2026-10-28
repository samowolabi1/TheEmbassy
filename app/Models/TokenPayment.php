<?php

namespace App\Models;

use App\Contracts\Payable;
use App\Models\Concerns\HasPayments;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tokens paid for one or more months in a row, starting at `from_month`.
 */
#[Fillable(['from_month', 'months', 'amount', 'method'])]
class TokenPayment extends Model implements Payable
{
    use HasFactory, HasPayments;

    public const AWAITING_PAYMENT = 'awaiting_payment';

    public const PAID = 'paid';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_month' => 'date',
            'months' => 'integer',
            'amount' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function lastMonth(): Carbon
    {
        return $this->from_month->copy()->startOfMonth()->addMonthsNoOverflow($this->months - 1);
    }

    /**
     * "Oct 2026" or "Oct – Dec 2026".
     */
    public function periodLabel(): string
    {
        $first = $this->from_month;
        $last = $this->lastMonth();

        return $this->months === 1 ? $first->format('M Y')
            : $first->format($first->year === $last->year ? 'M' : 'M Y').' – '.$last->format('M Y');
    }

    public function markPaid(): void
    {
        $this->forceFill(['status' => self::PAID, 'paid_at' => now()])->save();
    }

    public function feeModule(): string
    {
        return 'tokens';
    }

    public function paymentSucceeded(Payment $payment): void
    {
        $this->markPaid();
    }

    public function paymentReturnUrl(Payment $payment): string
    {
        return route('account.tokens', ['paid' => $this->id]);
    }
}
