<?php

namespace App\Mail;

use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * Monday's email: the week's declaration, announcements, new messages and articles, and events.
 */
class WeeklyNewsletter extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $issue  from App\Services\Messaging\Newsletter::content()
     */
    public function __construct(public array $issue, public ?NewsletterSubscriber $subscriber = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->issue['subject']);
    }

    /**
     * One-click unsubscribe in mail apps (RFC 8058).
     */
    public function headers(): Headers
    {
        if (! $this->subscriber) {
            return new Headers;
        }

        return new Headers(text: [
            'List-Unsubscribe' => '<'.$this->subscriber->unsubscribeUrl().'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.newsletter', with: ['unsubscribeUrl' => $this->subscriber?->unsubscribeUrl() ?? '#']);
    }
}
