<?php

namespace App\Mail;

use App\Models\Gift;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A receipt for an online gift.
 */
class GiftReceipt extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Gift $gift) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Thank you for your gift to '.config('church.short_name'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.gift-receipt');
    }
}
