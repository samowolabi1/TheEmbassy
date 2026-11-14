<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A category for money in (income) or money out (expenses). Made from config/finance.php the
 * first time they're needed; after that the church adds, renames and hides them in admin.
 */
class MoneyCategory extends Model
{
    public const IN = 'in';

    public const OUT = 'out';

    protected $fillable = ['kind', 'name', 'sort', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort' => 'integer'];
    }

    /**
     * @param  Builder<MoneyCategory>  $query
     */
    public function scopeOfKind(Builder $query, string $kind): void
    {
        $query->where('kind', $kind)->orderBy('sort')->orderBy('name');
    }

    /**
     * The category names people can choose for this kind.
     *
     * @return list<string>
     */
    public static function names(string $kind): array
    {
        self::ensureDefaults();

        return static::ofKind($kind)->where('is_active', true)->pluck('name')->all();
    }

    public static function ensureDefaults(): void
    {
        foreach ([self::IN => 'finance.income_categories', self::OUT => 'finance.expense_categories'] as $kind => $key) {
            if (static::where('kind', $kind)->exists()) {
                continue;
            }
            foreach (array_values(config($key, [])) as $i => $name) {
                static::create(['kind' => $kind, 'name' => $name, 'sort' => $i]);
            }
        }
    }
}
