<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Days a pastor isn't seeing anyone (travel, conference, leave).
 */
#[Fillable(['user_id', 'starts_on', 'ends_on', 'note'])]
class PastorTimeOff extends Model
{
    protected $table = 'pastor_time_off';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }
}
