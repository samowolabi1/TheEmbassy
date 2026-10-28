<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A message in the library: Sunday, midweek, podcast or special service.
 */
#[Fillable(['title', 'kind', 'sermon_series_id', 'preacher', 'preached_on', 'scripture', 'length', 'url', 'swatch', 'is_published'])]
class Sermon extends Model
{
    use HasFactory;

    public const KINDS = [
        'sunday' => 'Sunday service',
        'midweek' => 'Midweek',
        'podcast' => 'Podcast',
        'special' => 'Special service',
    ];

    public const SWATCHES = ['navy', 'gold', 'mid', 'ice', 'wash', 'night'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'preached_on' => 'date',
            'is_published' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<SermonSeries, $this>
     */
    public function series(): BelongsTo
    {
        return $this->belongsTo(SermonSeries::class, 'sermon_series_id');
    }

    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('is_published', true)->orderByDesc('preached_on')->orderByDesc('id');
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? 'Message';
    }

    /**
     * The line under the title, e.g. "Crowned for More · Week 3" or "Midweek prayer".
     */
    public function meta(): string
    {
        if ($this->series) {
            $week = $this->series->sermons()->where('preached_on', '<=', $this->preached_on)->where('is_published', true)->count();

            return $this->series->fullTitle().' · Week '.max(1, $week);
        }

        return $this->preacher ? $this->kindLabel().' · '.$this->preacher : $this->kindLabel();
    }
}
