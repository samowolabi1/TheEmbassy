<?php

namespace App\Mail;

use App\Models\VendorSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A seller's receipt for a subscription, with the dates it covers.
 */
class SubscriptionActivated extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public VendorSubscription $subscription) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your '.config('church.brand.market_name').' subscription is active');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.subscription-activated');
    }
}
