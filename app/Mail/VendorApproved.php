<?php

namespace App\Mail;

use App\Models\Shop;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells a vendor their shop is approved, with a link to set their password.
 */
class VendorApproved extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Shop $shop, public string $setPasswordUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->shop->name.' is approved on '.config('church.brand.market_name'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.vendor-approved');
    }
}
