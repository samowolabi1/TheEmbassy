<?php

namespace Database\Factories;

use App\Models\Announcement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tag' => fake()->randomElement(['This Sunday', 'Units', 'Prayer']),
            'tone' => fake()->randomElement(['wash', 'ice']),
            'title' => fake()->sentence(5),
            'body' => fake()->sentence(10),
            'url' => null,
            'published_at' => now()->subDay(),
        ];
    }

    /**
     * Not published yet.
     */
    public function draft(): static
    {
        return $this->state(fn () => ['published_at' => null]);
    }

    /**
     * Goes live in the future.
     */
    public function scheduled(): static
    {
        return $this->state(fn () => ['published_at' => now()->addDays(3)]);
    }
}
