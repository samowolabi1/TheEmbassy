<?php

namespace Database\Factories;

use App\Models\Shop;
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
            'bank_name' => 'Test Bank',
            'bank_code' => '058',
            'account_number' => '0123456789',
        ];
    }

    /**
     * Approved, with a Paystack payout account, so it can sell.
     */
    public function selling(): static
    {
        return $this->state(fn () => ['status' => Shop::APPROVED, 'approved_at' => now(), 'subaccount_code' => 'ACCT_vendor']);
    }
}
