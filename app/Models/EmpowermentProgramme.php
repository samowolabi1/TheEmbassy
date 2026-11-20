<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * A youth empowerment programme people apply to: its banner and words, when applications are
 * open, who can apply (age), the choices on offer and the questions on its form.
 */
#[Fillable(['slug', 'title', 'tagline', 'intro', 'opens_on', 'closes_on', 'max_choices', 'min_age', 'max_age', 'is_published', 'thank_you'])]
class EmpowermentProgramme extends Model
{
    protected function casts(): array
    {
        return ['opens_on' => 'date', 'closes_on' => 'date', 'is_published' => 'boolean', 'max_choices' => 'integer', 'min_age' => 'integer', 'max_age' => 'integer'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return HasMany<EmpowermentTrack, $this>
     */
    public function tracks(): HasMany
    {
        return $this->hasMany(EmpowermentTrack::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<EmpowermentQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(EmpowermentQuestion::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<EmpowermentApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(EmpowermentApplication::class);
    }

    /**
     * The programme the public page shows: an open one first, then the latest published.
     */
    public static function current(): ?self
    {
        $published = static::where('is_published', true)->latest('opens_on')->latest('id')->get();

        return $published->first(fn (self $p) => $p->isOpen()) ?? $published->first();
    }

    public function isOpen(): bool
    {
        return $this->is_published
            && (! $this->opens_on || $this->opens_on->lte(today()))
            && (! $this->closes_on || $this->closes_on->gte(today()));
    }

    /**
     * "Open", "Opens 1 Feb", "Closed" or "Not published".
     */
    public function statusLabel(): string
    {
        return match (true) {
            ! $this->is_published => 'Not published',
            $this->isOpen() => 'Open',
            $this->opens_on && $this->opens_on->gt(today()) => 'Opens '.$this->opens_on->format('j M'),
            default => 'Closed',
        };
    }

    public function bannerUrl(): ?string
    {
        return $this->banner_path ? Storage::disk('public')->url($this->banner_path) : null;
    }

    public function ageRule(): ?string
    {
        return match (true) {
            $this->min_age && $this->max_age => "For ages {$this->min_age} to {$this->max_age}",
            (bool) $this->min_age => "For ages {$this->min_age} and over",
            (bool) $this->max_age => "For ages up to {$this->max_age}",
            default => null,
        };
    }
}
