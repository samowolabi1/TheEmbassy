<?php

namespace Database\Factories;

use App\Models\Shop;
use App\Models\VendorPlan;
use App\Models\VendorSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorSubscription>
 */
class VendorSubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory()->approved(),
            'vendor_plan_id' => fn () => VendorPlan::firstOrCreate(['key' => 'monthly'], ['name' => 'Monthly', 'months' => 1, 'price' => 500000, 'is_active' => true])->id,
            'months' => 1,
            'list_price' => 500000,
            'discount' => 0,
            'amount' => 500000,
        ];
    }

    /**
     * Paid and running now.
     */
    public function active(): static
    {
        return $this->state(fn () => ['status' => VendorSubscription::ACTIVE, 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
    }

    /**
     * Paid, but already run out.
     */
    public function expired(): static
    {
        return $this->state(fn () => ['status' => VendorSubscription::ACTIVE, 'starts_at' => now()->subMonths(2), 'ends_at' => now()->subMonth()]);
    }
}
