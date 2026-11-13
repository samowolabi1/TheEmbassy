<?php

namespace App\Mail;

use App\Models\Suggestion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The church's answer to a member's suggestion.
 */
class SuggestionAnswered extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Suggestion $suggestion) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'About your suggestion: '.$this->suggestion->subject);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.suggestion-answered');
    }
}
