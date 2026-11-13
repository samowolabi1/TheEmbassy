<?php

namespace App\Mail;

use App\Models\MediaRequest;
use App\Models\Unit;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * News about a media request, to the person who asked or to the media team.
 */
class MediaRequestUpdate extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public MediaRequest $mediaRequest, public string $headline, public string $body, public bool $forTeam) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->headline.' ('.$this->mediaRequest->reference.')');
    }

    public function content(): Content
    {
        $unit = Unit::ofKind('media');

        return new Content(view: 'mail.media-request', with: [
            'link' => $this->forTeam && $unit ? route('unit.requests.show', [$unit, $this->mediaRequest]) : route('media-requests.show', $this->mediaRequest),
        ]);
    }
}
