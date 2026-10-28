<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Equipment taken out by a member, and its return.
 */
#[Fillable(['member_id', 'out_at', 'due_on', 'note'])]
class EquipmentLoan extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'out_at' => 'datetime',
            'due_on' => 'date',
            'returned_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Equipment, $this>
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function isOverdue(): bool
    {
        return ! $this->returned_at && $this->due_on?->lt(today());
    }
}
