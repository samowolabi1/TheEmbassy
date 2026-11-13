<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A folder in the media unit's file library, e.g. "Sunday services 2026" or "Logos & brand".
 */
#[Fillable(['name', 'description'])]
class MediaFolder extends Model
{
    /**
     * @return HasMany<MediaFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(MediaFile::class);
    }
}
