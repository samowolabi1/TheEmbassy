<?php

namespace App\Mail;

use App\Models\UnitMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A unit leader's message to the unit.
 */
class UnitMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public UnitMessage $unitMessage) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->unitMessage->unit->name.': '.$this->unitMessage->subject);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.unit-message');
    }
}
