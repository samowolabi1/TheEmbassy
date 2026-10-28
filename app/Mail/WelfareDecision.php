<?php

namespace App\Mail;

use App\Models\WelfareCase;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells a member what's been decided about their welfare request.
 */
class WelfareDecision extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public WelfareCase $case) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'About your welfare request ('.$this->case->reference.')');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.welfare-decision');
    }
}
