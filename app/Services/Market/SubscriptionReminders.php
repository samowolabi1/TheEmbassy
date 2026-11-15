<?php

namespace App\Services\Market;

use App\Mail\SubscriptionEnding;
use App\Mail\SubscriptionLapsed;
use App\Models\PushSubscription;
use App\Models\ReminderLog;
use App\Models\Shop;
use App\Services\Messaging\Reminders;
use App\Services\Messaging\Termii;
use App\Services\Messaging\WebPush;
use Illuminate\Support\Carbon;

/**
 * Reminds sellers before their subscription runs out (and again the day before, if that's on),
 * and tells them when it has. Staff set the timing, the ways it's sent and the wording on
 * admin › Reminders & greetings. Each end date is only reminded about once per step, so running
 * this daily is safe.
 */
class SubscriptionReminders
{
    public function __construct(private Reminders $reminders, private WebPush $push, private Termii $sms) {}

    /**
     * @return array{ending: int, lapsed: int}
     */
    public function send(): array
    {
        $sent = ['ending' => 0, 'lapsed' => 0];
        $s = Reminders::settings('market');
        if (! $s['enabled']) {
            return $sent;
        }
        $days = max(1, $s['timing']);

        Shop::approved()->with('user')->whereHas('subscriptions', fn ($q) => $q->where('status', 'active'))->each(function (Shop $shop) use (&$sent, $s, $days) {
            $until = $shop->subscribedUntil();
            if (! $until) {
                return;
            }

            if ($until->isFuture() && $until->lte(now()->addDays($days)) && ! $shop->reminded_for?->equalTo($until)) {
                $this->remind($shop, $until, $s) && $sent['ending']++;
                // A reminder this close to the end also counts as the final one.
                $shop->forceFill(['reminded_for' => $until, 'final_reminded_for' => $until->lte(now()->addDay()) ? $until : $shop->final_reminded_for])->save();
            } elseif ($s['final'] && $until->isFuture() && $until->lte(now()->addDay()) && ! $shop->final_reminded_for?->equalTo($until)) {
                $this->remind($shop, $until, $s) && $sent['ending']++;
                $shop->forceFill(['final_reminded_for' => $until])->save();
            } elseif ($s['lapsed'] && $until->isPast() && $until->gt(now()->subDays(30)) && ! $shop->lapse_noticed_for?->equalTo($until)) {
                if ($s['email']) {
                    $this->reminders->mail($shop->email, new SubscriptionLapsed($shop, $until)) && $sent['lapsed']++;
                } else {
                    $sent['lapsed']++;
                }
                $this->notify($shop, $shop->name.' is hidden from the market', 'Your subscription ended on '.$until->format('j F').'. Renew and your listings are back straight away.', $s);
                $shop->forceFill(['lapse_noticed_for' => $until])->save();
            }
        });

        return $sent;
    }

    private function remind(Shop $shop, Carbon $until, array $s): bool
    {
        $text = Reminders::render($s['text'], [
            'name' => $shop->owner_name ?: $shop->name,
            'shop' => $shop->name,
            'market' => config('church.brand.market_name'),
            'end_date' => $until->format('l j F'),
            'days_left' => Reminders::daysLeft((int) ceil(now()->floatDiffInDays($until))),
        ]);
        $mailed = $s['email'] && $this->reminders->mail($shop->email, new SubscriptionEnding($shop, $until, $text));
        $notified = $this->notify($shop, 'Your subscription ends '.$until->format('D j M'), $text, $s);

        return $mailed || $notified;
    }

    private function notify(Shop $shop, string $title, string $body, array $s): bool
    {
        $url = route('vendor.subscription.index');
        $done = false;
        if ($s['push'] && $shop->user_id && $this->push->configured()) {
            $subs = PushSubscription::where('user_id', $shop->user_id)->get();
            if ($subs->isNotEmpty()) {
                $this->push->send($subs, ['title' => $title, 'body' => $body, 'url' => $url, 'tag' => 'market-renewal']);
                $done = true;
            }
        }
        $phone = $shop->whatsapp ?: $shop->phone;
        if ($s['sms'] && $phone && $this->sms->configured()) {
            $done = $this->sms->send([$phone], mb_substr($body, 0, 459)) > 0 || $done;
        }
        ReminderLog::create(['kind' => 'market', 'user_id' => $shop->user_id, 'shop_id' => $shop->id, 'title' => mb_substr($title, 0, 160), 'body' => $body, 'url' => $url,
            'channels' => collect(['push', 'sms', 'email'])->filter(fn ($c) => $s[$c])->join(','), 'created_at' => now()]);

        return $done;
    }
}
