<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * How much a category may spend in a month.
 */
#[Fillable(['category', 'month', 'amount'])]
class Budget extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'month' => 'date',
            'amount' => 'integer',
        ];
    }
}
