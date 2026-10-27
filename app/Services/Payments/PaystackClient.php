<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
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
     * @param  array<string, mixed>|null  $split  a dynamic split; replaces the default church subaccount split
     *
     * @throws RuntimeException when Paystack refuses or can't be reached
     */
    public function initialize(Payment $payment, string $callbackUrl, array $metadata = [], ?array $split = null): string
    {
        $body = [
            'email' => $payment->email,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'reference' => $payment->reference,
            'callback_url' => $callbackUrl,
            'metadata' => $metadata,
        ];

        if ($split) {
            $body['split'] = $split;
        } elseif ($subaccount = config('services.paystack.subaccount')) {
            // Church money settles to the church's subaccount; Donaitech's fee (if any) goes to the main account.
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
     * Nigerian banks Paystack can pay out to, as [code => name]. Cached for a day.
     *
     * @return array<string, string>
     */
    public function banks(): array
    {
        if (! $this->enabled()) {
            return [];
        }

        return Cache::remember('paystack.banks', now()->addDay(), fn () => collect(
            $this->call(fn (PendingRequest $http) => $http->get('/bank', ['country' => 'nigeria', 'perPage' => 100]))
        )->pluck('name', 'code')->sort()->all());
    }

    /**
     * Create a subaccount so a vendor's share of each sale settles to their own bank account.
     *
     * @return array{code: string, account_name: ?string}
     *
     * @throws RuntimeException
     */
    public function createSubaccount(string $businessName, string $bankCode, string $accountNumber, string $email): array
    {
        $data = $this->call(fn (PendingRequest $http) => $http->post('/subaccount', [
            'business_name' => $businessName,
            'settlement_bank' => $bankCode,
            'account_number' => $accountNumber,
            // Shares are set per order with a dynamic split, so this default is unused.
            'percentage_charge' => 0,
            'primary_contact_email' => $email,
        ]));

        return [
            'code' => $data['subaccount_code'] ?? throw new RuntimeException('Paystack did not return a subaccount code.'),
            'account_name' => $data['account_name'] ?? null,
        ];
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
     * @param  callable(PendingRequest): Response  $send
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
