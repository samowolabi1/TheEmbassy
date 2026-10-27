<?php

namespace App\Models;

use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['slug', 'title', 'tag', 'summary', 'venue', 'starts_at', 'ends_at', 'is_featured', 'is_published'])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_featured' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return HasMany<TicketType, $this>
     */
    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * @return HasMany<TicketOrder, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(TicketOrder::class);
    }

    /**
     * @return HasManyThrough<Ticket, TicketOrder, $this>
     */
    public function tickets(): HasManyThrough
    {
        return $this->hasManyThrough(Ticket::class, TicketOrder::class);
    }

    /**
     * Published events that haven't finished yet, soonest first.
     */
    #[Scope]
    protected function upcoming(Builder $query): void
    {
        $query->where('is_published', true)
            ->where(fn ($q) => $q->where('starts_at', '>=', now()->startOfDay())->orWhere('ends_at', '>=', now()))
            ->orderBy('starts_at');
    }

    public function hasStarted(): bool
    {
        return $this->starts_at->isPast();
    }

    /**
     * "Free", "₦2,000" or "From ₦2,000", from the ticket types. Null when there are no tickets.
     */
    public function priceLabel(): ?string
    {
        $prices = $this->ticketTypes->pluck('price');
        if ($prices->isEmpty()) {
            return null;
        }
        if ($prices->max() === 0) {
            return 'Free';
        }
        $lowest = $prices->filter()->min();

        return ($prices->unique()->count() > 1 ? 'From ' : '').'₦'.number_format($lowest / 100);
    }

    /**
     * Whether anyone has bought or reserved a place, so ticket types are kept, not removed.
     */
    public function hasSales(): bool
    {
        return $this->tickets()->exists();
    }
}
