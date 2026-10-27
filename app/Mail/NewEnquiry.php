<?php

namespace App\Mail;

use App\Models\Enquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells a seller a buyer has messaged them about a listing.
 */
class NewEnquiry extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Enquiry $enquiry) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New enquiry: '.($this->enquiry->product?->name ?? $this->enquiry->shop->name),
            replyTo: $this->enquiry->email ? [new Address($this->enquiry->email, $this->enquiry->name)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.new-enquiry');
    }
}
