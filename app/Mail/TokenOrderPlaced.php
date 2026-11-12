<?php

namespace App\Mail;

use App\Models\TokenOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells a seller someone ordered with tokens.
 */
class TokenOrderPlaced extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public TokenOrder $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'New token order: '.$this->order->product_name);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.token-order');
    }
}
