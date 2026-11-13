<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * News about a visit with a pastor, to the member or to the pastor.
 */
class AppointmentUpdate extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Appointment $appointment, public string $headline, public string $body, public bool $forStaff) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->headline);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.appointment', with: [
            'link' => $this->forStaff ? route('admin.appointments.index') : route('account.appointments'),
        ]);
    }
}
