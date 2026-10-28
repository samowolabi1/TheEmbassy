<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A piece of the media unit's kit: cameras, mics, mixers, laptops, cables.
 */
#[Fillable(['name', 'category', 'tag', 'serial', 'location', 'condition', 'purchased_on', 'value', 'notes'])]
class Equipment extends Model
{
    protected $table = 'equipment';

    public const CATEGORIES = [
        'camera' => 'Cameras',
        'audio' => 'Audio and microphones',
        'lighting' => 'Lighting',
        'computer' => 'Computers and streaming',
        'network' => 'Internet and network',
        'display' => 'Screens and projectors',
        'cable' => 'Cables and accessories',
        'other' => 'Other',
    ];

    public const STATUSES = [
        'available' => 'Available',
        'checked_out' => 'Checked out',
        'repair' => 'Being repaired',
        'retired' => 'Retired',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purchased_on' => 'date',
            'value' => 'integer',
        ];
    }

    /**
     * @return HasMany<EquipmentLoan, $this>
     */
    public function loans(): HasMany
    {
        return $this->hasMany(EquipmentLoan::class);
    }

    /**
     * @return HasOne<EquipmentLoan, $this>
     */
    public function currentLoan(): HasOne
    {
        return $this->hasOne(EquipmentLoan::class)->whereNull('returned_at')->latestOfMany('out_at');
    }

    /**
     * @return HasMany<EquipmentIssue, $this>
     */
    public function issues(): HasMany
    {
        return $this->hasMany(EquipmentIssue::class);
    }
}
