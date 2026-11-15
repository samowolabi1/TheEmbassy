<?php

namespace App\Services\Messaging;

use App\Mail\ReminderMail;
use App\Models\Broadcast;
use App\Models\Member;
use App\Models\PushSubscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends announcements by email, WhatsApp and app notification, a batch per scheduler run.
 * (Text messages are no longer sent, to save fees.)
 */
class Broadcaster
{
    public const PUSH_BATCH = 500;

    public const EMAIL_BATCH = 100;

    public const WHATSAPP_BATCH = 50;

    public function __construct(private WhatsApp $whatsapp, private WebPush $push) {}

    /**
     * Approved members with an email address (theirs, or their login's).
     *
     * @return Builder<Member>
     */
    public function emailAudience(): Builder
    {
        return Member::where('status', Member::ACTIVE)->where(fn ($q) => $q->whereNotNull('email')->where('email', '!=', '')->orWhereNotNull('user_id'));
    }

    /**
     * Approved members with a phone number who haven't turned WhatsApp messages off.
     *
     * @return Builder<Member>
     */
    public function whatsappAudience(): Builder
    {
        return Member::where('status', Member::ACTIVE)->where('notify_sms', true)->whereNotNull('phone')->where('phone', '!=', '');
    }

    /**
     * How many people each way would reach now.
     *
     * @return array{email: int, whatsapp: int, push: int}
     */
    public function reach(): array
    {
        return ['email' => $this->emailAudience()->count(), 'whatsapp' => $this->whatsappAudience()->count(), 'push' => PushSubscription::count()];
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

        // Text messages are no longer sent; older broadcasts that asked for them just finish.
        $emailDone = ! $broadcast->uses('email') || $this->emailBatch($broadcast);
        $whatsappDone = ! $broadcast->uses('whatsapp') || $this->whatsappBatch($broadcast);
        $pushDone = ! $broadcast->uses('push') || $this->pushBatch($broadcast);

        if ($emailDone && $whatsappDone && $pushDone) {
            $broadcast->update(['status' => 'sent', 'finished_at' => now()]);
        }

        return true;
    }

    private function emailBatch(Broadcast $broadcast): bool
    {
        $members = $this->emailAudience()->with('user')->where('id', '>', $broadcast->email_cursor)->orderBy('id')->take(self::EMAIL_BATCH)->get();
        if ($members->isEmpty()) {
            return true;
        }
        $sent = $failed = 0;
        $done = [];
        foreach ($members as $member) {
            $email = mb_strtolower((string) ($member->user?->email ?? $member->email));
            if ($email === '' || isset($done[$email])) {
                continue;
            }
            $done[$email] = true;
            try {
                Mail::to($email)->send(new ReminderMail($broadcast->title, $broadcast->message, $broadcast->url, 'Read more'));
                $sent++;
            } catch (Throwable $e) {
                $failed++;
                Log::error('Could not email an announcement', ['broadcast' => $broadcast->id, 'error' => $e->getMessage()]);
            }
        }
        $broadcast->update(['email_cursor' => $members->last()->id, 'email_sent' => $broadcast->email_sent + $sent, 'email_failed' => $broadcast->email_failed + $failed]);

        return $members->count() < self::EMAIL_BATCH;
    }

    private function whatsappBatch(Broadcast $broadcast): bool
    {
        $members = $this->whatsappAudience()->where('id', '>', $broadcast->whatsapp_cursor)->orderBy('id')->take(self::WHATSAPP_BATCH)->get(['id', 'phone']);
        if ($members->isEmpty()) {
            return true;
        }
        // One message per number, even if two members share a phone.
        $phones = $members->map(fn (Member $m) => Member::internationalPhone($m->phone));
        $numbers = $phones->filter()->unique()->values()->all();
        $sent = $this->whatsapp->ready() ? $this->whatsapp->send($numbers, $broadcast->title, $broadcast->message) : 0;
        $broadcast->update([
            'whatsapp_cursor' => $members->last()->id,
            'whatsapp_sent' => $broadcast->whatsapp_sent + $sent,
            'whatsapp_failed' => $broadcast->whatsapp_failed + ($members->count() - $sent),
        ]);

        return $members->count() < self::WHATSAPP_BATCH;
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
