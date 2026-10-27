<?php

namespace App\Contracts;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Something that can be paid for online: a notice, a ticket order, a market order.
 */
interface Payable
{
    /**
     * @return MorphMany<Payment, $this>
     */
    public function payments(): MorphMany;

    /**
     * Which platform fee applies (a key of config('platform.fees')).
     */
    public function feeModule(): string;

    /**
     * Called once, inside a database transaction, when Paystack confirms the payment.
     */
    public function paymentSucceeded(Payment $payment): void;

    /**
     * Where to send the payer after Paystack checkout.
     */
    public function paymentReturnUrl(Payment $payment): string;
}
