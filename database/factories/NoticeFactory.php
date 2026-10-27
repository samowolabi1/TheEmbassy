<?php

namespace Database\Factories;

use App\Models\Notice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notice>
 */
class NoticeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'advertiser_type' => 'business',
            'organisation' => fake()->company(),
            'contact_name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => '0803 000 0000',
            'category' => 'Business',
            'title' => fake()->sentence(4),
            'body' => fake()->sentence(14),
            'url' => null,
            'package' => 'week',
            'days' => 7,
            'featured' => false,
            'amount' => 500000,
        ];
    }

    /**
     * Paid for and waiting for an admin.
     */
    public function inReview(): static
    {
        return $this->state(fn () => ['status' => Notice::IN_REVIEW, 'paid_at' => now()]);
    }

    /**
     * Approved and running.
     */
    public function live(): static
    {
        return $this->state(fn () => [
            'status' => Notice::APPROVED,
            'paid_at' => now()->subDay(),
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(6),
        ]);
    }
}
