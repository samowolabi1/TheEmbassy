<?php

namespace App\Services\Market;

use App\Mail\SubscriptionEnding;
use App\Mail\SubscriptionLapsed;
use App\Models\Shop;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails sellers a week before their subscription runs out, and again when it has.
 * Each end date is only emailed about once, so running this daily is safe.
 */
class SubscriptionReminders
{
    public const DAYS_BEFORE = 7;

    /**
     * @return array{ending: int, lapsed: int}
     */
    public function send(): array
    {
        $sent = ['ending' => 0, 'lapsed' => 0];

        Shop::approved()->whereHas('subscriptions', fn ($q) => $q->where('status', 'active'))->each(function (Shop $shop) use (&$sent) {
            $until = $shop->subscribedUntil();
            if (! $until) {
                return;
            }

            if ($until->isFuture() && $until->lte(now()->addDays(self::DAYS_BEFORE)) && ! $shop->reminded_for?->equalTo($until)) {
                $this->mail($shop, new SubscriptionEnding($shop, $until)) && $sent['ending']++;
                $shop->forceFill(['reminded_for' => $until])->save();
            } elseif ($until->isPast() && $until->gt(now()->subDays(30)) && ! $shop->lapse_noticed_for?->equalTo($until)) {
                $this->mail($shop, new SubscriptionLapsed($shop, $until)) && $sent['lapsed']++;
                $shop->forceFill(['lapse_noticed_for' => $until])->save();
            }
        });

        return $sent;
    }

    private function mail(Shop $shop, object $mailable): bool
    {
        try {
            Mail::to($shop->email)->send($mailable);

            return true;
        } catch (Throwable $e) {
            Log::error('Could not send subscription reminder', ['shop' => $shop->id, 'error' => $e->getMessage()]);

            return false;
        }
    }
}
