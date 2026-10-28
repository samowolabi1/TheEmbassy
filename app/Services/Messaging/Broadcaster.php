<?php

namespace App\Services\Messaging;

use App\Models\Broadcast;
use App\Models\Member;
use App\Models\PushSubscription;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Sends announcements by text message and app notification, a batch per scheduler run.
 */
class Broadcaster
{
    /** Numbers per run on the bulk route; the one-at-a-time dnd route sends fewer. */
    public const SMS_BATCH = 1000;

    public const DND_BATCH = 60;

    public const PUSH_BATCH = 500;

    public function __construct(private Termii $sms, private WebPush $push) {}

    /**
     * Active members who have a phone number and haven't turned text messages off.
     *
     * @return Builder<Member>
     */
    public function smsAudience(): Builder
    {
        return Member::where('status', Member::ACTIVE)->where('notify_sms', true)->whereNotNull('phone')->where('phone', '!=', '');
    }

    /**
     * How many people each channel would reach now.
     *
     * @return array{sms: int, push: int}
     */
    public function reach(): array
    {
        return ['sms' => $this->smsAudience()->count(), 'push' => PushSubscription::count()];
    }

    /**
     * Work on the oldest unfinished broadcast. Returns true if there was one.
     */
    public function sendBatch(): bool
    {
        $broadcast = Broadcast::whereIn('status', ['queued', 'sending'])->orderBy('id')->first();
        if (! $broadcast) {
            return false;
        }
        if ($broadcast->status === 'queued') {
            $broadcast->update(['status' => 'sending', 'started_at' => now()]);
        }

        $smsDone = ! $broadcast->uses('sms') || $this->smsBatch($broadcast);
        $pushDone = ! $broadcast->uses('push') || $this->pushBatch($broadcast);

        if ($smsDone && $pushDone) {
            $broadcast->update(['status' => 'sent', 'finished_at' => now()]);
        }

        return true;
    }

    /**
     * @return bool whether every text has now been sent (or the channel gave up)
     */
    private function smsBatch(Broadcast $broadcast): bool
    {
        if ($broadcast->sms_error) {
            return true;
        }
        $limit = config('services.termii.channel') === 'dnd' ? self::DND_BATCH : self::SMS_BATCH;
        $members = $this->smsAudience()->where('id', '>', $broadcast->sms_cursor)->orderBy('id')->take($limit)->get(['id', 'phone']);
        if ($members->isEmpty()) {
            return true;
        }

        // One text per number, even if two members share a phone.
        $phones = $members->map(fn (Member $m) => Member::internationalPhone($m->phone));
        $invalid = $phones->filter(fn ($p) => $p === null)->count();
        $numbers = $phones->filter()->unique()->values()->all();

        try {
            $sent = $this->sms->send($numbers, $broadcast->message);
        } catch (Throwable $e) {
            // Stop texting (likely a setup or balance problem) rather than retrying every minute.
            $broadcast->update(['sms_error' => $e->getMessage(), 'sms_failed' => $broadcast->sms_failed + $members->count()]);

            return true;
        }

        $broadcast->update([
            'sms_cursor' => $members->last()->id,
            'sms_sent' => $broadcast->sms_sent + $sent,
            'sms_failed' => $broadcast->sms_failed + $invalid,
        ]);

        return $members->count() < $limit;
    }

    private function pushBatch(Broadcast $broadcast): bool
    {
        $subscriptions = PushSubscription::where('id', '>', $broadcast->push_cursor)->orderBy('id')->take(self::PUSH_BATCH)->get();
        if ($subscriptions->isEmpty()) {
            return true;
        }

        $result = $this->push->send($subscriptions, [
            'title' => $broadcast->title,
            'body' => $broadcast->message,
            'url' => $broadcast->url,
            'tag' => 'broadcast-'.$broadcast->id,
        ]);
        $broadcast->update([
            'push_cursor' => $subscriptions->max('id'),
            'push_sent' => $broadcast->push_sent + $result['sent'],
            'push_failed' => $broadcast->push_failed + $result['failed'],
        ]);

        return $subscriptions->count() < self::PUSH_BATCH;
    }
}
