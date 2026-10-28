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
 * Tells a seller their subscription has run out and their listings are hidden.
 */
class SubscriptionLapsed extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Shop $shop, public Carbon $until) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->shop->name.' is hidden from '.config('church.brand.market_name'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.subscription-lapsed');
    }
}
