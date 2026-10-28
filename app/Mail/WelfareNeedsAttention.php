<?php

namespace App\Mail;

use App\Models\WelfareCase;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the welfare team (or a pastor) a case needs them. It leaves out the person's situation;
 * that stays in the app.
 */
class WelfareNeedsAttention extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public WelfareCase $case, public string $heading) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->heading.' ('.$this->case->reference.')');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.welfare-attention');
    }
}
