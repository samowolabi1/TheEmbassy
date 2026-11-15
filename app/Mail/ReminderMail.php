<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A service reminder, thank-you greeting or feedback request by email.
 */
class ReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $headline, public string $body, public ?string $url = null, public string $button = 'Open the app') {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->headline);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.reminder');
    }
}
