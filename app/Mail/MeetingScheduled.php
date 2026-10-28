<?php

namespace App\Mail;

use App\Models\UnitMeeting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells a unit's members about a meeting.
 */
class MeetingScheduled extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public UnitMeeting $meeting) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->meeting->unit->name.': '.$this->meeting->title.', '.$this->meeting->starts_at->format('D j M, g:i A'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.unit-meeting');
    }
}
