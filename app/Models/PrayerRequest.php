<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'contact', 'request', 'is_private'])]
class PrayerRequest extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_private' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    /**
     * Requests nobody on the team has marked as read yet.
     */
    #[Scope]
    protected function unread(Builder $query): void
    {
        $query->whereNull('read_at');
    }
}
