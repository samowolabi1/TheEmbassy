<?php

namespace App\Contracts;

use App\Models\Payment;

/**
 * A payable whose money is divided between several Paystack subaccounts, e.g. a market order
 * (vendor, church and platform), instead of the default church-plus-platform split.
 */
interface SplitsPayment
{
    /**
     * Paystack's dynamic split for this payment: what goes into the "split" field of the checkout.
     *
     * @return array<string, mixed>
     */
    public function paystackSplit(Payment $payment): array;
}
