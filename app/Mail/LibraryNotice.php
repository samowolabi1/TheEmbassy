<?php

namespace App\Mail;

use App\Models\Member;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * News from the church library: a book is ready, due back soon, overdue, or returned.
 */
class LibraryNotice extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Member $member, public string $headline, public string $body, public string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->headline);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.library');
    }
}
