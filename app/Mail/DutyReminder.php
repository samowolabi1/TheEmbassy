<?php

namespace App\Mail;

use App\Models\Duty;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Reminds a member they're serving at a coming service.
 */
class DutyReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Duty $duty) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'You\'re on the '.$this->duty->unit->name.' rota: '.$this->duty->service->held_on->format('l j M'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.duty-reminder');
    }
}
