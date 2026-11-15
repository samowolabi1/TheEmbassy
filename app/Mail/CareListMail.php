<?php

namespace App\Mail;

use App\Mail\Concerns\IsNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A short email to staff with a list: overdue follow-ups, the weekly summary, visits to make.
 */
class CareListMail extends Mailable
{
    use IsNotification, Queueable, SerializesModels;

    /**
     * @param  list<array{0: string, 1?: string}>  $items  each a line and an optional detail
     * @param  array<string, int|string>  $figures  label => number, shown as a row of figures
     */
    public function __construct(
        public string $headline,
        public string $intro,
        public array $items = [],
        public ?string $url = null,
        public string $button = 'Open',
        public array $figures = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->headline);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.care-list');
    }
}
