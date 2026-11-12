<?php

namespace App\Mail;

use App\Models\WelfarePackageApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells a member what was decided about their welfare package application.
 */
class WelfarePackageDecision extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public WelfarePackageApplication $application) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->application->package->title.': '.$this->application->statusLabel());
    }

    public function content(): Content
    {
        return new Content(view: 'mail.welfare-package');
    }
}
