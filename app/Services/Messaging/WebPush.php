<?php

namespace App\Services\Messaging;

use App\Models\PushSubscription;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush as Client;

/**
 * Notifications to phones and browsers that installed the app and allowed notifications.
 */
class WebPush
{
    public function configured(): bool
    {
        return filled(config('services.webpush.public_key')) && filled(config('services.webpush.private_key'));
    }

    /**
     * Send to each subscription. Ones the push service says are gone are deleted.
     *
     * @param  Collection<int, PushSubscription>  $subscriptions
     * @param  array{title: string, body: string, url?: string|null, tag?: string}  $notification
     * @return array{sent: int, failed: int}
     */
    public function send(Collection $subscriptions, array $notification): array
    {
        if (! $this->configured() || $subscriptions->isEmpty()) {
            return ['sent' => 0, 'failed' => $subscriptions->count()];
        }

        $client = new Client(['VAPID' => [
            'subject' => config('app.url'),
            'publicKey' => config('services.webpush.public_key'),
            'privateKey' => config('services.webpush.private_key'),
        ]], ['TTL' => 86400, 'urgency' => 'normal'], logger: Log::channel());
        $client->setReuseVAPIDHeaders(true);

        $payload = json_encode([
            'title' => $notification['title'],
            'body' => $notification['body'],
            'url' => $notification['url'] ?? route('home'),
            'tag' => $notification['tag'] ?? null,
            'icon' => route('app.icon', 192),
        ], JSON_UNESCAPED_SLASHES);

        $byEndpoint = $subscriptions->keyBy('endpoint');
        foreach ($subscriptions as $sub) {
            $client->queueNotification(Subscription::create([
                'endpoint' => $sub->endpoint,
                'publicKey' => $sub->public_key,
                'authToken' => $sub->auth_token,
                'contentEncoding' => $sub->content_encoding,
            ]), $payload);
        }

        $sent = $failed = 0;
        foreach ($client->flush() as $report) {
            $sub = $byEndpoint->get($report->getEndpoint());
            if ($report->isSuccess()) {
                $sent++;
                $sub?->forceFill(['last_used_at' => now(), 'failures' => 0])->save();

                continue;
            }
            $failed++;
            if ($report->isSubscriptionExpired() || ($sub && $sub->failures >= 4)) {
                $sub?->delete();
            } else {
                $sub?->increment('failures');
                Log::info('Push not delivered', ['reason' => $report->getReason()]);
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }
}
