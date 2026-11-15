<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A message from the church's Message centre.
 */
class CampaignMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $headline, public string $body, public ?string $url = null, public string $kind = 'member') {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->headline);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.campaign');
    }
}
