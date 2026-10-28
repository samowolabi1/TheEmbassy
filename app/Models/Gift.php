<?php

namespace App\Models;

use App\Contracts\Payable;
use App\Mail\GiftReceipt;
use App\Models\Concerns\HasPayments;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
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
 * A gift to a fund: given online through Paystack, or counted at church and recorded by staff.
 */
#[Fillable(['fund_id', 'member_id', 'name', 'email', 'amount', 'method', 'given_on', 'counted_by', 'notes'])]
class Gift extends Model implements Payable
{
    use HasFactory, HasPayments;

    public const AWAITING_PAYMENT = 'awaiting_payment';

    public const PAID = 'paid';

    public const METHODS = [
        'online' => 'Online',
        'cash' => 'Cash',
        'transfer' => 'Bank transfer',
        'cheque' => 'Cheque',
    ];

    protected static function booted(): void
    {
        static::creating(function (Gift $gift) {
            $gift->code ??= ($gift->method === 'online' ? 'RTE-' : 'CASH-').Str::upper(Str::random(8));
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
            'amount' => 'integer',
            'given_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Fund, $this>
     */
    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    #[Scope]
    protected function paid(Builder $query): void
    {
        $query->where('status', self::PAID);
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->method] ?? ucfirst($this->method);
    }

    public function feeModule(): string
    {
        return 'giving';
    }

    public function paymentSucceeded(Payment $payment): void
    {
        $this->forceFill(['status' => self::PAID, 'given_on' => today()])->save();

        if ($this->email) {
            DB::afterCommit(function () {
                try {
                    Mail::to($this->email)->send(new GiftReceipt($this));
                } catch (Throwable $e) {
                    Log::error('Could not email gift receipt', ['gift' => $this->code, 'error' => $e->getMessage()]);
                }
            });
        }
    }

    public function paymentReturnUrl(Payment $payment): string
    {
        return $this->url();
    }

    /**
     * The giver's private thank-you page.
     */
    public function url(): string
    {
        return URL::signedRoute('give.thanks', $this);
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
