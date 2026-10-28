<?php

namespace App\Mail;

use App\Models\FollowUp;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells a member of the care team someone has been given to them to contact.
 */
class FollowUpAssigned extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public FollowUp $followUp) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Please follow up with '.$this->followUp->personName());
    }

    public function content(): Content
    {
        return new Content(view: 'mail.follow-up-assigned');
    }
}
