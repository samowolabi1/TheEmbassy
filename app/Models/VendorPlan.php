<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * A subscription period sellers can buy (monthly, quarterly, 6 months, 1 year, 3 years).
 */
#[Fillable(['key', 'name', 'months', 'price', 'is_active'])]
class VendorPlan extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'months' => 'integer',
            'price' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Make sure every period in config/market.php has a row. New ones start unpriced and switched off,
     * so nothing is sold until an admin sets a price.
     *
     * @return Collection<int, VendorPlan>
     */
    public static function catalogue(): Collection
    {
        foreach (config('market.plans') as $key => $plan) {
            static::firstOrCreate(['key' => $key], ['name' => $plan['name'], 'months' => $plan['months']]);
        }

        return static::query()->orderBy('months')->get();
    }

    /**
     * Switched on and priced.
     */
    #[Scope]
    protected function available(Builder $query): void
    {
        $query->where('is_active', true)->where('price', '>', 0)->orderBy('months');
    }

    public function priceLabel(): string
    {
        return '₦'.number_format($this->price / 100);
    }

    /**
     * The price per month, to compare longer plans.
     */
    public function monthlyEquivalent(): int
    {
        return intdiv($this->price, max(1, $this->months));
    }
}
