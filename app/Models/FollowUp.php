<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Someone to contact: a first-timer to welcome, a member who's been away, a new member to settle in.
 */
#[Fillable(['member_id', 'first_timer_id', 'reason', 'note', 'reference_on', 'assigned_to', 'due_on'])]
class FollowUp extends Model
{
    public const REASONS = [
        'first_timer' => 'First-timer',
        'absent' => 'Not seen lately',
        'new_member' => 'New member',
        'other' => 'Pastoral care',
    ];

    public const OUTCOMES = [
        'reached' => 'Spoke with them',
        'no_answer' => 'No answer, left a message',
        'visited' => 'Visited',
        'prayed' => 'Prayed with them',
        'returned' => 'Back in church',
        'other' => 'Other',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reference_on' => 'date',
            'due_on' => 'date',
            'done_at' => 'datetime',
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
     * @return BelongsTo<FirstTimer, $this>
     */
    public function firstTimer(): BelongsTo
    {
        return $this->belongsTo(FirstTimer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function doer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }

    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereNull('done_at');
    }

    public function personName(): string
    {
        return $this->member?->name ?? $this->firstTimer?->name ?? 'Someone';
    }

    public function phone(): ?string
    {
        return $this->member?->phone ?? $this->firstTimer?->phone;
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? 'Follow-up';
    }

    public function isOverdue(): bool
    {
        return ! $this->done_at && $this->due_on->lt(today());
    }

    /**
     * Mark as done. Calling a first-timer also ticks off their card.
     */
    public function complete(User $by, string $outcome, ?string $note = null): void
    {
        $this->forceFill(['done_at' => now(), 'done_by' => $by->id, 'outcome' => $outcome, 'outcome_note' => $note])->save();

        if ($this->firstTimer && ! $this->firstTimer->followed_up_at) {
            $this->firstTimer->forceFill(['followed_up_at' => now(), 'followed_up_by' => $by->id, 'follow_up_note' => $note ?? (self::OUTCOMES[$outcome] ?? null)])->save();
        }
    }
}
