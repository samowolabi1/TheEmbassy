<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A birthday design the media unit uploaded: their finished picture, with the member's photo,
 * name and day placed on it.
 */
#[Fillable(['name', 'layout'])]
class BirthdayDesign extends Model
{
    /** Where things go to start with, in percent of the card (photo size is its width across). */
    public const DEFAULTS = [
        'photo_shape' => 'circle', // circle, rounded, none
        'photo_x' => 50,
        'photo_y' => 36,
        'photo_size' => 38,
        'photo_border' => '#ffffff',
        'name_y' => 70,
        'name_size' => 72,
        'name_font' => 'poppins',
        'name_color' => '#ffffff',
        'name_upper' => true,
        'name_shadow' => true,
        'show_date' => true,
        'date_color' => '#ffffff',
        'show_message' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['layout' => 'array'];
    }

    /**
     * @return array<string, mixed>
     */
    public function layout(): array
    {
        return [...self::DEFAULTS, ...($this->layout ?? [])];
    }

    public function key(): string
    {
        return 'upload-'.$this->id;
    }

    public function backgroundUrl(): string
    {
        return Storage::disk('public')->url($this->background_path);
    }

    /**
     * Changes whenever a design is added, changed or removed, so saved cards are drawn again.
     */
    public static function stamp(): string
    {
        return static::count().':'.static::max('updated_at');
    }
}
