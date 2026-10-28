<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An announcement sent out by text message and app notification. The scheduler sends it in
 * batches, keeping its place so a restart never texts anyone twice.
 */
#[Fillable(['announcement_id', 'title', 'message', 'url', 'channels', 'status', 'sms_cursor', 'sms_sent', 'sms_failed', 'sms_error', 'push_cursor', 'push_sent', 'push_failed', 'created_by', 'started_at', 'finished_at'])]
class Broadcast extends Model
{
    public const CHANNELS = ['sms' => 'Text message (SMS)', 'push' => 'App notification'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Announcement, $this>
     */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function uses(string $channel): bool
    {
        return in_array($channel, $this->channels ?? [], true);
    }

    /**
     * SMS pages per person: 160 characters for one, 153 each when it runs longer.
     */
    public static function smsPages(string $text): int
    {
        $length = mb_strlen($text);

        return $length <= 160 ? 1 : (int) ceil($length / 153);
    }
}
