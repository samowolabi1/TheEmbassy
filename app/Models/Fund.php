<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What a gift is for: tithe, offering, building fund and so on.
 */
#[Fillable(['name', 'description', 'target', 'is_active', 'sort'])]
class Fund extends Model
{
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target' => 'integer',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * @return HasMany<Gift, $this>
     */
    public function gifts(): HasMany
    {
        return $this->hasMany(Gift::class);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort')->orderBy('id');
    }

    /**
     * Create the funds in config/finance.php the first time, so the church starts with the usual ones.
     */
    public static function ensureDefaults(): void
    {
        if (static::query()->doesntExist()) {
            foreach (config('finance.funds') as $i => $fund) {
                static::create([...$fund, 'sort' => $i]);
            }
        }
    }

    /**
     * Everything received into this fund, in kobo.
     */
    public function raised(): int
    {
        return (int) $this->gifts()->where('status', Gift::PAID)->sum('amount');
    }
}
