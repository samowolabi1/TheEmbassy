<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Someone the church is helping: a request, the decision, support given and the case log.
 * Only the welfare team, pastors and the assigned case worker see a case.
 */
#[Fillable(['member_id', 'beneficiary_name', 'beneficiary_phone', 'category', 'summary', 'details', 'urgency', 'amount_requested', 'assigned_member_id', 'next_check_on'])]
class WelfareCase extends Model
{
    public const OPEN = ['new', 'in_review', 'awaiting_pastor', 'approved', 'supported'];

    protected static function booted(): void
    {
        static::creating(function (WelfareCase $case) {
            $case->reference ??= 'WF-'.Str::upper(Str::random(6));
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
            'amount_requested' => 'integer',
            'amount_approved' => 'integer',
            'decided_at' => 'datetime',
            'escalated_at' => 'datetime',
            'next_check_on' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * The welfare unit member visiting or calling them.
     *
     * @return BelongsTo<Member, $this>
     */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'assigned_member_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * @return HasMany<WelfareNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(WelfareNote::class);
    }

    /**
     * @return HasMany<WelfarePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(WelfarePayment::class);
    }

    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereIn('status', self::OPEN);
    }

    public function personName(): string
    {
        return $this->member?->name ?? $this->beneficiary_name ?? 'Unknown';
    }

    public function phone(): ?string
    {
        return $this->member?->phone ?? $this->beneficiary_phone;
    }

    public function categoryLabel(): string
    {
        return config("welfare.categories.{$this->category}", 'Other');
    }

    public function statusLabel(): string
    {
        return config("welfare.statuses.{$this->status}", ucfirst($this->status));
    }

    public function paid(): int
    {
        return (int) $this->payments()->sum('amount');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    /**
     * Above the HOD's limit, a pastor has to approve.
     */
    public static function needsPastor(int $kobo): bool
    {
        return $kobo > (int) config('church.welfare.approval_limit') * 100;
    }

    /**
     * What the person who asked sees: no internal notes, just where it's up to.
     */
    public function requesterStatus(): string
    {
        return match ($this->status) {
            'new', 'in_review', 'awaiting_pastor' => 'The welfare team is looking into it',
            'approved' => 'Approved',
            'supported', 'closed' => 'Support given',
            'declined' => 'We couldn\'t help this time',
            default => $this->statusLabel(),
        };
    }
}
