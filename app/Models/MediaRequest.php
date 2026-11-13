<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Something a unit or the church office asks the media unit to make or cover: a flyer, a video,
 * photos of an event. It's taken on by a media team member, delivered, and closed by whoever asked.
 */
#[Fillable(['unit_id', 'type', 'title', 'details', 'event_on', 'needed_by'])]
class MediaRequest extends Model
{
    public const TYPES = [
        'flyer' => 'Flyer or poster',
        'graphic' => 'Social media graphic',
        'video' => 'Video (promo, recap or testimony)',
        'photography' => 'Photo coverage of an event',
        'livestream' => 'Livestream or recording',
        'slides' => 'Slides or lyrics on screen',
        'other' => 'Something else',
    ];

    public const STATUSES = [
        'new' => 'New',
        'in_progress' => 'In progress',
        'delivered' => 'Delivered',
        'completed' => 'Done',
        'declined' => 'Declined',
        'cancelled' => 'Cancelled',
    ];

    /** Still with the media team. */
    public const OPEN = ['new', 'in_progress'];

    /** How many days' notice the media team asks for. */
    public const NOTICE_DAYS = 7;

    protected static function booted(): void
    {
        static::creating(function (MediaRequest $r) {
            do {
                $r->reference = 'MR-'.Str::upper(Str::random(6));
            } while (static::where('reference', $r->reference)->exists());
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
            'event_on' => 'date',
            'needed_by' => 'date',
            'accepted_at' => 'datetime',
            'delivered_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'assigned_member_id');
    }

    /**
     * @return HasMany<MediaRequestComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(MediaRequestComment::class)->oldest('id');
    }

    /**
     * @return HasMany<MediaFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(MediaFile::class)->oldest('id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? Str::headline($this->type);
    }

    /**
     * The status as the person who asked sees it.
     */
    public function statusLabel(): string
    {
        return match ($this->status) {
            'new' => 'Waiting for the media team',
            'in_progress' => ($this->assignee ? Member::firstNameOf($this->assignee->name).' is working on it' : 'In progress'),
            'delivered' => 'Ready: please check it',
            default => self::STATUSES[$this->status] ?? $this->status,
        };
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    /**
     * Whether it's still with the team and due within two days (or past due).
     */
    public function isUrgent(): bool
    {
        return $this->isOpen() && $this->needed_by->lte(today()->addDays(2));
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->needed_by->lt(today());
    }

    /**
     * Who it's from: the unit, or the person's name for the church office.
     */
    public function fromLabel(): string
    {
        return $this->unit?->name ?? 'Church office';
    }
}
