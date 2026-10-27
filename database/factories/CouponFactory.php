<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->bothify('SAVE####')),
            'percent_off' => 20,
            'max_uses' => null,
            'expires_at' => null,
            'is_active' => true,
        ];
    }

    public function percent(int $percent): static
    {
        return $this->state(fn () => ['percent_off' => $percent]);
    }
}
