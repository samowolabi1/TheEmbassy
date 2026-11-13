<?php

namespace App\Mail;

use App\Models\Testimony;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells a member what happened to the testimony they shared.
 */
class TestimonyUpdate extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Testimony $testimony, public string $headline, public string $body) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->headline);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.testimony');
    }
}
