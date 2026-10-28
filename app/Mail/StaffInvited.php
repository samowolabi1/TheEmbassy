<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells someone they've been given a staff role, with a set-password link if they're new.
 */
class StaffInvited extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public ?string $setPasswordUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'You have staff access to '.config('church.short_name'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.staff-invited');
    }
}
