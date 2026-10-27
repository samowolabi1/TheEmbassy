<?php

namespace App\Services\Payments;

use App\Contracts\Payable;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creating payments and settling them once Paystack confirms them.
 */
class Payments
{
    public function __construct(private PaystackClient $paystack) {}

    public function enabled(): bool
    {
        return $this->paystack->enabled();
    }

    /**
     * Record a pending payment and return the Paystack checkout link.
     *
     * @throws \RuntimeException when Paystack can't start the checkout
     */
    public function checkout(Model&Payable $payable, int $amountInKobo, string $email): string
    {
        $payment = $payable->payments()->create([
            'reference' => 'RT-'.Str::upper(Str::random(16)),
            'email' => $email,
            'amount' => $amountInKobo,
            'platform_fee' => self::platformFee($payable->feeModule(), $amountInKobo),
            'currency' => 'NGN',
        ]);

        return $this->paystack->initialize($payment, route('payments.callback'), [
            'payable' => class_basename($payable),
            'payable_id' => $payable->getKey(),
        ]);
    }

    /**
     * Donaitech's fee on an amount, from config('platform.fees'), in kobo.
     */
    public static function platformFee(string $module, int $amountInKobo): int
    {
        $percent = max(0, min(100, (float) config('platform.fees.'.$module, 0)));

        return (int) round($amountInKobo * $percent / 100);
    }

    /**
     * Check a payment with Paystack and settle it. Safe to call many times.
     */
    public function confirm(Payment $payment): Payment
    {
        if (! $payment->isPaid()) {
            $this->settle($payment, $this->paystack->verify($payment->reference));
        }

        return $payment->refresh();
    }

    /**
     * Apply what Paystack says about a payment. Runs once per payment even if the
     * callback and webhook arrive together; amounts must match what we asked for.
     *
     * @param  array<string, mixed>  $data  Paystack's transaction data
     */
    public function settle(Payment $payment, array $data): void
    {
        DB::transaction(function () use ($payment, $data) {
            $payment = Payment::whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            if ($payment->isPaid() || ($data['reference'] ?? null) !== $payment->reference) {
                return;
            }

            $status = $data['status'] ?? null;
            $matches = (int) ($data['amount'] ?? -1) === $payment->amount
                && strtoupper((string) ($data['currency'] ?? '')) === $payment->currency;

            if ($status === 'success' && $matches) {
                $payment->forceFill([
                    'status' => Payment::SUCCESS,
                    'channel' => $data['channel'] ?? null,
                    'paid_at' => now(),
                    'gateway_response' => $data,
                ])->save();

                $payment->payable->paymentSucceeded($payment);
            } elseif (in_array($status, ['failed', 'abandoned', 'reversed'], true) || ($status === 'success' && ! $matches)) {
                $payment->forceFill(['status' => Payment::FAILED, 'gateway_response' => $data])->save();
            }
        });
    }
}
