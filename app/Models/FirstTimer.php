<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A visitor's first-timer card, and whether someone has called them.
 */
#[Fillable(['name', 'phone', 'email', 'invited_by', 'visited_on', 'wants_to_join', 'notes'])]
class FirstTimer extends Model
{
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visited_on' => 'date',
            'wants_to_join' => 'boolean',
            'followed_up_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function follower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'followed_up_by');
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
