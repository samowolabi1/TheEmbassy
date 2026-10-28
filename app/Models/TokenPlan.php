<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Monthly member dues ("tokens"): e.g. standard, student, exempt (₦0).
 */
#[Fillable(['name', 'amount', 'description', 'is_default'])]
class TokenPlan extends Model
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
            'amount' => 'integer',
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Member, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    /**
     * Create the plans in config/finance.php the first time.
     */
    public static function ensureDefaults(): void
    {
        if (static::query()->doesntExist()) {
            foreach (config('finance.token_plans') as $plan) {
                static::create($plan);
            }
        }
    }

    public static function default(): ?self
    {
        return static::firstWhere('is_default', true);
    }

    public function isExempt(): bool
    {
        return $this->amount === 0;
    }

    public function amountLabel(): string
    {
        return '₦'.number_format($this->amount / 100);
    }
}
