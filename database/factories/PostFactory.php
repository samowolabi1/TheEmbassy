<?php

namespace Database\Factories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = rtrim(fake()->unique()->sentence(5), '.');

        return [
            'title' => $title,
            'slug' => Post::uniqueSlug($title),
            'topic' => 'Faith',
            'dek' => fake()->sentence(),
            'body' => fake()->paragraphs(4, true),
            'swatch' => 'navy',
            'author_name' => fake()->name(),
            'published_at' => now()->subDays(fake()->numberBetween(1, 60)),
        ];
    }

    public function draft(): static
    {
        return $this->state(['published_at' => null]);
    }
}
