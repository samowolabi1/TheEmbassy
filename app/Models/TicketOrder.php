<?php

namespace App\Models;

use App\Contracts\Payable;
use App\Mail\TicketsConfirmed;
use App\Models\Concerns\HasPayments;
use Database\Factories\TicketOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * One booking: who booked, for which event, and the tickets it holds.
 */
#[Fillable(['code', 'name', 'email', 'phone', 'total'])]
class TicketOrder extends Model implements Payable
{
    /** @use HasFactory<TicketOrderFactory> */
    use HasFactory, HasPayments;

    public const AWAITING_PAYMENT = 'awaiting_payment';

    public const CONFIRMED = 'confirmed';

    /** How long an unpaid order holds its places. */
    public const HOLD_MINUTES = 30;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total' => 'integer',
            'confirmed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Orders whose tickets count against a limit.
     */
    #[Scope]
    protected function holdingPlaces(Builder $query): void
    {
        // Grouped, so the OR can't leak into the query this is used inside.
        $query->where(fn ($q) => $q->where('status', self::CONFIRMED)
            ->orWhere(fn ($q) => $q->where('status', self::AWAITING_PAYMENT)->where('created_at', '>', now()->subMinutes(self::HOLD_MINUTES))));
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::CONFIRMED;
    }

    /**
     * Confirm the booking and email the tickets (after the database change is saved).
     */
    public function confirm(): void
    {
        if ($this->isConfirmed()) {
            return;
        }

        $this->forceFill(['status' => self::CONFIRMED, 'confirmed_at' => now()])->save();

        DB::afterCommit(function () {
            try {
                Mail::to($this->email)->send(new TicketsConfirmed($this));
            } catch (Throwable $e) {
                // The tickets are still on the order page; don't fail the booking over an email.
                Log::error('Could not email tickets', ['order' => $this->code, 'error' => $e->getMessage()]);
            }
        });
    }

    public function feeModule(): string
    {
        return 'tickets';
    }

    public function paymentSucceeded(Payment $payment): void
    {
        $this->confirm();
    }

    public function paymentReturnUrl(Payment $payment): string
    {
        return $this->url();
    }

    /**
     * The buyer's private link to their tickets.
     */
    public function url(): string
    {
        return URL::signedRoute('tickets.show', $this);
    }
}
