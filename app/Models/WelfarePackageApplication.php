<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A member's application for a welfare package. The reference is shown when they collect.
 */
#[Fillable(['welfare_package_id', 'member_id', 'household_size', 'note', 'status', 'decision_note', 'decided_by', 'decided_at', 'collected_at'])]
class WelfarePackageApplication extends Model
{
    public const STATUSES = [
        'pending' => 'Waiting for the welfare team',
        'approved' => 'Approved: ready to collect',
        'waitlist' => 'On the waiting list',
        'declined' => 'Not this time',
        'collected' => 'Collected',
        'withdrawn' => 'Withdrawn',
    ];

    protected static function booted(): void
    {
        static::creating(function (WelfarePackageApplication $a) {
            do {
                $a->reference = 'PK-'.Str::upper(Str::random(6));
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
            'decided_at' => 'datetime',
            'collected_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WelfarePackage, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(WelfarePackage::class, 'welfare_package_id');
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }
}
