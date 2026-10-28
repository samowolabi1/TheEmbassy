<?php

namespace App\Services\Messaging;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Text messages through Termii (termii.com), which delivers to Nigerian networks.
 */
class Termii
{
    /** Termii accepts up to 10,000 numbers in one bulk request. */
    public const BULK_LIMIT = 10_000;

    public function configured(): bool
    {
        return filled(config('services.termii.api_key')) && filled(config('services.termii.sender_id'));
    }

    /**
     * Send one message to many numbers (digits with country code). Returns how many were accepted.
     * The "dnd" route has no bulk send, so those go one at a time.
     *
     * @param  list<string>  $numbers
     */
    public function send(array $numbers, string $message): int
    {
        if (! $this->configured()) {
            throw new RuntimeException('Text messages aren\'t set up: add the Termii API key and sender ID in Settings & roles.');
        }
        if ($numbers === []) {
            return 0;
        }

        if (config('services.termii.channel') === 'dnd') {
            $sent = 0;
            foreach ($numbers as $number) {
                $this->post('api/sms/send', $number, $message, 'dnd');
                $sent++;
            }

            return $sent;
        }

        foreach (array_chunk($numbers, self::BULK_LIMIT) as $chunk) {
            $this->post('api/sms/send/bulk', $chunk, $message, 'generic');
        }

        return count($numbers);
    }

    /**
     * @param  string|list<string>  $to
     */
    private function post(string $path, string|array $to, string $message, string $channel): void
    {
        $response = $this->http()->post($path, [
            'api_key' => config('services.termii.api_key'),
            'to' => $to,
            'from' => config('services.termii.sender_id'),
            'sms' => $message,
            'type' => 'plain',
            'channel' => $channel,
        ]);

        if ($response->failed()) {
            $reason = $response->json('message') ?? $response->json('error') ?? 'HTTP '.$response->status();
            throw new RuntimeException('Termii refused the message: '.(is_string($reason) ? $reason : json_encode($reason)));
        }
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.termii.base_url'), '/').'/')
            ->acceptJson()
            ->asJson()
            ->timeout(60);
    }
}
