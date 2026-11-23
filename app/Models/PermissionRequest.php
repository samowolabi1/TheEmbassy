<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A worker asking permission to be away from a service, or to come late,
 * with their reason. Their unit heads (HOD and assistant) and the pastors
 * see it and grant or decline it, with an optional note back.
 */
#[Fillable(['member_id', 'service_id', 'on_date', 'kind', 'arrive_at', 'reason'])]
class PermissionRequest extends Model
{
    public const KINDS = ['absent' => 'Won\'t be in church', 'late' => 'Coming late'];

    public const STATUSES = [
        'pending' => 'Waiting for an answer',
        'granted' => 'Permission granted',
        'declined' => 'Not granted',
        'cancelled' => 'Cancelled',
    ];

    protected function casts(): array
    {
        return [
            'answered_at' => 'datetime',
        ];
    }

    /**
     * Stored as a plain date, so it can be matched against a list of days.
     */
    protected function onDate(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->startOfDay() : null,
            set: fn ($value) => $value ? Carbon::parse($value)->toDateString() : null,
        );
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function answeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeUpcoming(Builder $query): void
    {
        $query->where('on_date', '>=', today()->toDateString());
    }

    /**
     * Requests from anyone serving in these units.
     *
     * @param  Builder<self>  $query
     * @param  array<int, int>  $unitIds
     */
    public function scopeForUnits(Builder $query, array $unitIds): void
    {
        $query->whereHas('member.units', fn ($q) => $q->whereIn('units.id', $unitIds));
    }

    /**
     * Who is on the rota at the requests' services, keyed "service-member", so each card
     * can say "arrange cover" without a query of its own.
     *
     * @param  iterable<self>  $requests
     * @return Collection<string, Collection<int, Duty>>
     */
    public static function rotaFor(iterable $requests): Collection
    {
        $requests = collect($requests)->filter->service_id;
        if ($requests->isEmpty()) {
            return collect();
        }

        return Duty::whereIn('service_id', $requests->pluck('service_id')->unique())->whereIn('member_id', $requests->pluck('member_id')->unique())
            ->with('unit')->get()->groupBy(fn (Duty $d) => $d->service_id.'-'.$d->member_id);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, ['pending', 'granted'], true) && $this->on_date->gte(today());
    }

    /**
     * "Won't be in church · Sunday service, Sun 30 Nov" or "Coming late (about 9:30 AM) · ...".
     */
    public function summary(): string
    {
        $kind = $this->kind === 'late'
            ? 'Coming late'.($this->arrive_at ? ' (about '.Carbon::createFromFormat('H:i', $this->arrive_at)->format('g:i A').')' : '')
            : self::KINDS['absent'];

        return $kind.' · '.$this->when();
    }

    public function when(): string
    {
        return ($this->service ? $this->service->label().', ' : '').$this->on_date->format('D j M');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Whether someone may see and answer it: a head of one of the worker's units, or a pastor.
     */
    public function canBeAnsweredBy(User $user): bool
    {
        if ($user->canManage('permissions')) {
            return true;
        }
        $member = $user->member;

        return $member !== null && $this->member->units->contains(fn (Unit $u) => $u->isLedBy($member));
    }
}
