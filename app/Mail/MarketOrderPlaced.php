<?php

namespace App\Mail;

use App\Models\MarketOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A paid market order: a receipt for the buyer, or a new-order alert for the vendor.
 */
class MarketOrderPlaced extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public MarketOrder $order, public bool $forVendor) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->forVendor
            ? 'New order '.$this->order->code.': '.$this->order->product->name
            : 'Your order from '.$this->order->shop->name);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.market-order');
    }
}
