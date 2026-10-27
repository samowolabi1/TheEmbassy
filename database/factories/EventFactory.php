<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(3),
            'title' => fake()->sentence(3),
            'tag' => 'Worship',
            'summary' => fake()->sentence(12),
            'venue' => 'Main sanctuary',
            'starts_at' => now()->addWeeks(2)->setTime(18, 0),
            'is_featured' => false,
            'is_published' => true,
        ];
    }
}
