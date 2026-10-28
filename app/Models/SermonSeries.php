<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A sermon series, shown as a card on the Watch page.
 */
#[Fillable(['title', 'accent', 'style', 'url', 'is_current'])]
class SermonSeries extends Model
{
    use HasFactory;

    public const STYLES = ['navy' => 'Navy', 'gold' => 'Gold', 'ice' => 'Ice blue'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Sermon, $this>
     */
    public function sermons(): HasMany
    {
        return $this->hasMany(Sermon::class);
    }

    public function fullTitle(): string
    {
        return trim($this->title.' '.$this->accent);
    }
}
