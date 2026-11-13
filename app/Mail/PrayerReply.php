<?php

namespace App\Mail;

use App\Models\PrayerRequest;
use App\Models\PrayerRequestReply;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A word from the prayer team to a member about their prayer request.
 */
class PrayerReply extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PrayerRequest $prayer, public PrayerRequestReply $reply) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'We\'re praying with you');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.prayer-reply');
    }
}
