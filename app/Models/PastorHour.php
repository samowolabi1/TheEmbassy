<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A pastor's regular time for seeing members, e.g. Tuesdays 10:00 to 13:00 in 30-minute visits.
 */
#[Fillable(['user_id', 'weekday', 'starts', 'ends', 'slot_minutes', 'mode', 'location'])]
class PastorHour extends Model
{
    public const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    public const MODES = ['both' => 'In person or by call', 'in_person' => 'In person', 'call' => 'Phone or video call'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function pastor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return list<string>
     */
    public function modes(): array
    {
        return $this->mode === 'both' ? ['in_person', 'call'] : [$this->mode];
    }
}
