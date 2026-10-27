<?php

namespace App\Models;

use Database\Factories\DeclarationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

#[Fillable(['week_of', 'lines'])]
class Declaration extends Model
{
    /** @use HasFactory<DeclarationFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'week_of' => 'date',
            'lines' => 'array',
        ];
    }

    /**
     * The declaration for the current week: the latest one whose week has started.
     */
    public static function current(): ?self
    {
        return static::where('week_of', '<=', today())->latest('week_of')->first();
    }

    /**
     * The Monday of the week containing the given day.
     */
    public static function weekStarting(Carbon|string $day): Carbon
    {
        return Carbon::parse($day)->startOfWeek(Carbon::MONDAY);
    }
}
