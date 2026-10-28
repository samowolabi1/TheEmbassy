<?php

namespace App\Mail;

use App\Models\Shop;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Tells a seller their subscription runs out soon.
 */
class SubscriptionEnding extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Shop $shop, public Carbon $until) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your '.config('church.brand.market_name').' subscription ends on '.$this->until->format('j F'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.subscription-ending');
    }
}
