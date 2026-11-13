<?php

namespace App\Services\Messaging;

use App\Models\Announcement;
use App\Models\BirthdayCelebration;
use App\Models\Broadcast;
use App\Models\UnitMessage;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A member's messages in one place: what the church sent out, announcements, and their unit's messages.
 */
class Inbox
{
    /** How far back the inbox goes. */
    public const DAYS = 90;

    /**
     * Newest first.
     *
     * @return Collection<int, array{kind: string, from: string, title: string, body: string, url: ?string, at: Carbon}>
     */
    public function items(User $user): Collection
    {
        $since = now()->subDays(self::DAYS);

        $broadcasts = Broadcast::whereIn('status', ['sending', 'sent'])->where('created_at', '>=', $since)->latest()->take(100)->get()
            ->map(fn (Broadcast $b) => [
                'kind' => 'broadcast', 'from' => config('church.short_name'), 'title' => $b->title,
                'body' => $b->message, 'url' => $b->url, 'at' => $b->created_at, 'key' => 'b'.$b->id,
            ]);
        // Announcements already sent as a broadcast appear once.
        $sent = Broadcast::whereNotNull('announcement_id')->pluck('announcement_id')->all();

        $announcements = Announcement::live()->where('published_at', '>=', $since)->whereNotIn('id', $sent)->take(100)->get()
            ->map(fn (Announcement $a) => [
                'kind' => 'announcement', 'from' => $a->tag ?: 'Announcement', 'title' => $a->title,
                'body' => (string) $a->body, 'url' => $a->url, 'at' => $a->published_at, 'key' => 'a'.$a->id,
            ]);

        $unitId = $user->member?->unit_id;
        $unit = $unitId ? UnitMessage::with('unit')->where('unit_id', $unitId)->where('created_at', '>=', $since)->latest()->take(100)->get()
            ->map(fn (UnitMessage $m) => [
                'kind' => 'unit', 'from' => $m->unit->name, 'title' => $m->subject,
                'body' => $m->body, 'url' => null, 'at' => $m->created_at, 'key' => 'u'.$m->id,
            ]) : collect();

        // Their own birthday wishes from the church, with their card.
        $birthdays = $user->member ? BirthdayCelebration::where('member_id', $user->member->id)->where('created_at', '>=', $since)->get()
            ->map(fn (BirthdayCelebration $c) => [
                'kind' => 'birthday', 'from' => config('church.short_name'), 'title' => 'Happy birthday! 🎉',
                'body' => 'The whole '.config('church.short_name').' family celebrates you today. May this new year bring you God\'s favour, joy and fruitfulness. Your birthday card is ready to download and share.',
                'url' => $c->cardUrl(), 'at' => $c->created_at, 'key' => 'c'.$c->id,
            ]) : collect();

        return $broadcasts->concat($announcements)->concat($unit)->concat($birthdays)->sortByDesc(fn ($i) => $i['at']->timestamp)->values();
    }

    public function unread(User $user): int
    {
        $since = $user->inbox_read_at ?? now()->subDays(self::DAYS);

        return $this->items($user)->filter(fn ($i) => $i['at']->gt($since))->count();
    }
}
