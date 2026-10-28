<?php

namespace Database\Factories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Choir', 'Ushering', 'Drama', 'Prayer', 'Hospitality', 'Sanctuary']).' '.fake()->unique()->numberBetween(1, 999),
            'kind' => 'general',
        ];
    }

    public function welfare(): static
    {
        return $this->state(fn () => ['kind' => 'welfare', 'name' => 'Welfare']);
    }

    public function media(): static
    {
        return $this->state(fn () => ['kind' => 'media', 'name' => 'Media & tech']);
    }
}
