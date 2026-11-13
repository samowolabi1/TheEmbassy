<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A member's visit with a pastor. It's asked for at an open time, then confirmed (perhaps at a new
 * time) or declined by the pastor or the church office. What the member wants to talk about is
 * seen only by that pastor and the pastor in charge.
 */
#[Fillable(['member_id', 'pastor_id', 'starts_at', 'ends_at', 'mode', 'topic', 'note'])]
class Appointment extends Model
{
    public const TOPICS = [
        'counselling' => 'Counselling',
        'prayer' => 'Prayer',
        'marriage' => 'Marriage, family or relationship',
        'career' => 'Career, school or business',
        'spiritual' => 'Spiritual growth or guidance',
        'membership' => 'Joining the church or a unit',
        'other' => 'Something else',
    ];

    public const MODES = ['in_person' => 'In person', 'call' => 'Phone or video call'];

    public const STATUSES = [
        'requested' => 'Waiting for the pastor to confirm',
        'confirmed' => 'Confirmed',
        'declined' => 'Not possible',
        'cancelled' => 'Cancelled',
        'completed' => 'Done',
        'missed' => 'Missed',
    ];

    /** Holding the time. */
    public const ACTIVE = ['requested', 'confirmed'];

    protected static function booted(): void
    {
        static::creating(function (Appointment $a) {
            do {
                $a->reference = 'AP-'.Str::upper(Str::random(6));
            } while (static::where('reference', $a->reference)->exists());
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'decided_at' => 'datetime',
            'reminded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pastor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pastor_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE, true);
    }

    public function isUpcoming(): bool
    {
        return $this->isActive() && $this->starts_at->isFuture();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function when(): string
    {
        return $this->starts_at->format('l j F, g:i A');
    }

    /**
     * The private note is for the pastor being seen and the pastor in charge.
     */
    public function noteVisibleTo(User $user): bool
    {
        return $user->id === $this->pastor_id || $user->isSuperAdmin();
    }
}
