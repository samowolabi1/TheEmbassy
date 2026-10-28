<?php

namespace App\Mail;

use App\Models\Member;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A gentle reminder that a member's tokens are behind.
 */
class TokenReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Member $member) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'A reminder about your tokens');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.token-reminder');
    }
}
