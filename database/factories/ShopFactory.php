<?php

namespace Database\Factories;

use App\Models\Shop;
use App\Models\VendorSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shop>
 */
class ShopFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'name' => fake()->unique()->company(),
            'owner_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '0803 000 0000',
            'category' => 'Food',
            'description' => fake()->sentence(12),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => Shop::APPROVED, 'approved_at' => now()]);
    }

    /**
     * Approved with a paid-up subscription, so its listings show in the market.
     */
    public function listed(): static
    {
        return $this->approved()->has(VendorSubscription::factory()->active(), 'subscriptions');
    }
}
