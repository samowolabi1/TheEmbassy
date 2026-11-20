<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One choice in a programme, e.g. "Catering and baking" or "Phone repair", with optional places.
 */
#[Fillable(['name', 'description', 'slots', 'position', 'is_active'])]
class EmpowermentTrack extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'slots' => 'integer'];
    }

    /**
     * @return BelongsTo<EmpowermentProgramme, $this>
     */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(EmpowermentProgramme::class, 'empowerment_programme_id');
    }

    /**
     * Everyone who chose it (with their rank: 1 = first choice).
     *
     * @return BelongsToMany<EmpowermentApplication, $this>
     */
    public function applicants(): BelongsToMany
    {
        return $this->belongsToMany(EmpowermentApplication::class, 'empowerment_application_track')->withPivot('rank');
    }

    /**
     * @return HasMany<EmpowermentApplication, $this>
     */
    public function accepted(): HasMany
    {
        return $this->hasMany(EmpowermentApplication::class)->whereIn('status', ['accepted', 'completed']);
    }

    /**
     * Places not yet taken by accepted applicants, or null when there's no limit.
     */
    public function placesLeft(): ?int
    {
        if (! $this->slots) {
            return null;
        }
        $taken = $this->accepted_count ?? $this->accepted()->count();

        return max(0, $this->slots - $taken);
    }
}
