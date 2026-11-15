<?php

namespace App\Models;

use Database\Factories\TicketTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'price', 'quantity', 'max_per_order', 'sales_end_at', 'sort'])]
class TicketType extends Model
{
    /** @use HasFactory<TicketTypeFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'quantity' => 'integer',
            'max_per_order' => 'integer',
            'sales_end_at' => 'datetime',
        ];
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

    public function isFree(): bool
    {
        return $this->price === 0;
    }

    /**
     * Tickets that count against the limit: confirmed ones, plus places held for recent unpaid orders.
     */
    public function taken(): int
    {
        // Pages that list ticket types load the count up front (withTaken), so it isn't asked
        // again for every type; buying always counts afresh.
        if (array_key_exists('taken_count', $this->attributes)) {
            return (int) $this->attributes['taken_count'];
        }

        return $this->tickets()->whereHas('order', fn ($q) => $q->holdingPlaces())->count();
    }

    /**
     * @param  Builder<TicketType>  $query
     */
    public function scopeWithTaken($query): void
    {
        $query->withCount(['tickets as taken_count' => fn ($q) => $q->whereHas('order', fn ($o) => $o->holdingPlaces())]);
    }

    /**
     * How many can still be bought, or null for no limit.
     */
    public function remaining(): ?int
    {
        return $this->quantity === null ? null : max(0, $this->quantity - $this->taken());
    }

    public function onSale(): bool
    {
        return ! $this->event->hasStarted()
            && ($this->sales_end_at === null || $this->sales_end_at->isFuture())
            && $this->remaining() !== 0;
    }
}
