<?php

namespace Database\Factories;

use App\Models\Declaration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Declaration>
 */
class DeclarationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'week_of' => now()->startOfWeek(),
            'lines' => [fake()->sentence(), fake()->sentence(), fake()->sentence(), fake()->sentence()],
        ];
    }
}
