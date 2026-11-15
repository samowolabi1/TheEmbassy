<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A pastoral visit: to a member or a visitor, at home, in hospital or elsewhere. Staff plan it,
 * choose who goes, and write a short report afterwards. A visit made for a follow-up closes it.
 */
#[Fillable(['member_id', 'first_timer_id', 'follow_up_id', 'name', 'phone', 'address', 'purpose', 'scheduled_at', 'notes', 'tell_them'])]
class Visit extends Model
{
    public const PURPOSES = [
        'pastoral' => 'Pastoral visit',
        'absent' => 'Not seen lately',
        'new_member' => 'Welcome visit',
        'first_timer' => 'Visitor follow-up',
        'hospital' => 'Hospital visit',
        'bereavement' => 'Bereavement',
        'celebration' => 'Celebration (new baby, wedding…)',
        'welfare' => 'Welfare',
        'prayer' => 'Prayer',
    ];

    public const OUTCOMES = [
        'visited' => 'Visited',
        'prayed' => 'Visited and prayed together',
        'not_home' => 'Not home',
        'rescheduled' => 'Moved to another day',
        'other' => 'Other',
    ];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'done_at' => 'datetime', 'reminded_at' => 'datetime', 'tell_them' => 'boolean'];
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
     * @return BelongsTo<FollowUp, $this>
     */
    public function followUp(): BelongsTo
    {
        return $this->belongsTo(FollowUp::class);
    }

    /**
     * Who's going.
     *
     * @return BelongsToMany<User, $this>
     */
    public function visitors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'visit_user')->orderBy('name');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function doer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }

    #[Scope]
    protected function planned(Builder $query): void
    {
        $query->where('status', 'planned');
    }

    public function purposeLabel(): string
    {
        return self::PURPOSES[$this->purpose] ?? 'Visit';
    }

    public function isLate(): bool
    {
        return $this->status === 'planned' && $this->scheduled_at->lt(now()->subHours(3));
    }

    /**
     * Record how it went. A visit for a follow-up closes that follow-up too.
     */
    public function complete(User $by, string $outcome, ?string $report): void
    {
        $this->forceFill(['status' => 'done', 'outcome' => $outcome, 'report' => $report, 'done_by' => $by->id, 'done_at' => now()])->save();
        if ($this->followUp && ! $this->followUp->done_at && in_array($outcome, ['visited', 'prayed'], true)) {
            $this->followUp->complete($by, $outcome === 'prayed' ? 'prayed' : 'visited', $report ? mb_substr($report, 0, 255) : 'Visited on '.$this->scheduled_at->format('j M'));
        }
    }
}
