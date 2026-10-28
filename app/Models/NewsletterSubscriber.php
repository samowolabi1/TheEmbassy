<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Someone on the weekly email list. They confirm from an email before anything else is sent,
 * and every email carries a one-click unsubscribe link.
 */
#[Fillable(['email', 'confirmed_at', 'unsubscribed_at'])]
class NewsletterSubscriber extends Model
{
    protected static function booted(): void
    {
        static::creating(fn (NewsletterSubscriber $s) => $s->token ??= Str::random(40));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
            'last_sent_at' => 'datetime',
        ];
    }

    /**
     * Confirmed and still subscribed.
     */
    #[Scope]
    protected function receiving(Builder $query): void
    {
        $query->whereNotNull('confirmed_at')->whereNull('unsubscribed_at');
    }

    public function isReceiving(): bool
    {
        return $this->confirmed_at !== null && $this->unsubscribed_at === null;
    }

    public function confirmUrl(): string
    {
        return route('newsletter.confirm', $this->token);
    }

    public function unsubscribeUrl(): string
    {
        return route('newsletter.unsubscribe', $this->token);
    }
}
