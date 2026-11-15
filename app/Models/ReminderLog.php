<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One reminder, greeting or feedback request sent to one person.
 */
class ReminderLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
