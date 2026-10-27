<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The few Paystack API calls the site needs: start a checkout and confirm a payment.
 */
class PaystackClient
{
    public function enabled(): bool
    {
        return filled(config('services.paystack.secret_key'));
    }

    /**
     * Start a Paystack checkout and return the page to send the payer to.
     *
     * @param  array<string, mixed>  $metadata
     *
     * @throws RuntimeException when Paystack refuses or can't be reached
     */
    public function initialize(Payment $payment, string $callbackUrl, array $metadata = []): string
    {
        $body = [
            'email' => $payment->email,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'reference' => $payment->reference,
            'callback_url' => $callbackUrl,
            'metadata' => $metadata,
        ];

        // Church money settles to the church's subaccount; Donaitech's fee (if any) goes to the main account.
        if ($subaccount = config('services.paystack.subaccount')) {
            $body['subaccount'] = $subaccount;
            $body['transaction_charge'] = $payment->platform_fee;
            $body['bearer'] = 'subaccount';
        }

        $data = $this->call(fn (PendingRequest $http) => $http->post('/transaction/initialize', $body));

        return $data['authorization_url'] ?? throw new RuntimeException('Paystack did not return a checkout link.');
    }

    /**
     * Ask Paystack for the current state of a transaction.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public function verify(string $reference): array
    {
        return $this->call(fn (PendingRequest $http) => $http->get('/transaction/verify/'.rawurlencode($reference)));
    }

    /**
     * Check that a webhook really came from Paystack.
     */
    public function validSignature(string $payload, ?string $signature): bool
    {
        $secret = (string) config('services.paystack.secret_key');

        return $secret !== '' && $signature !== null && hash_equals(hash_hmac('sha512', $payload, $secret), $signature);
    }

    /**
     * @param  callable(PendingRequest): \Illuminate\Http\Client\Response  $send
     * @return array<string, mixed>
     */
    private function call(callable $send): array
    {
        if (! $this->enabled()) {
            throw new RuntimeException('Online payments are not switched on.');
        }

        try {
            $response = $send(Http::baseUrl(config('services.paystack.base_url'))
                ->withToken(config('services.paystack.secret_key'))
                ->acceptJson()
                ->timeout(15));
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach Paystack.', previous: $e);
        }

        if (! $response->successful() || ! $response->json('status')) {
            throw new RuntimeException('Paystack: '.($response->json('message') ?? 'request failed'));
        }

        return $response->json('data') ?? [];
    }
}
