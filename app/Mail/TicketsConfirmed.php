<?php

namespace App\Mail;

use App\Models\TicketOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when a booking is confirmed, with a link to the tickets and their QR codes.
 */
class TicketsConfirmed extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public TicketOrder $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your tickets for '.$this->order->event->title);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.tickets-confirmed');
    }
}
