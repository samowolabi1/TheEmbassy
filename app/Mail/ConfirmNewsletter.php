<?php

namespace App\Mail;

use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Asks a new subscriber to confirm, so nobody is signed up by someone else.
 */
class ConfirmNewsletter extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public NewsletterSubscriber $subscriber) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirm your weekly email from '.config('church.short_name'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.newsletter-confirm');
    }
}
