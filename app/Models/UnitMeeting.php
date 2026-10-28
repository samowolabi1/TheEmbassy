<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A unit meeting (rehearsal, planning, prayer), at a place or online, with attendance and minutes.
 */
#[Fillable(['title', 'starts_at', 'ends_at', 'location', 'online_url', 'agenda', 'minutes'])]
class UnitMeeting extends Model
{
    protected static function booted(): void
    {
        static::creating(function (UnitMeeting $meeting) {
            $meeting->secret ??= Str::random(40);
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
     * @return BelongsToMany<Member, $this>
     */
    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(Member::class)->withPivot('mode', 'checked_in_at');
    }

    /**
     * Members check in from 30 minutes before the start until the end (or two hours in).
     */
    public function checkInIsOpen(?Carbon $at = null): bool
    {
        $at ??= now();

        return $at->between($this->starts_at->copy()->subMinutes(30), ($this->ends_at ?? $this->starts_at->copy()->addHours(2)));
    }

    /**
     * The code the leader shows or reads out; it changes every minute, like the service one.
     */
    public function code(?Carbon $at = null): string
    {
        $step = intdiv(($at ?? now())->getTimestamp(), 60);

        return str_pad((string) (hexdec(substr(hash_hmac('sha256', 'meeting:'.$step, $this->secret), 0, 8)) % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    public function acceptsCode(?string $code): bool
    {
        $code = preg_replace('/\D/', '', (string) $code);
        foreach ([0, 1, 2] as $minutesAgo) {
            if (strlen($code) === 6 && hash_equals($this->code(now()->subMinutes($minutesAgo)), $code)) {
                return true;
            }
        }

        return false;
    }

    public function isUpcoming(): bool
    {
        return ($this->ends_at ?? $this->starts_at->copy()->addHours(2))->isFuture();
    }
}
