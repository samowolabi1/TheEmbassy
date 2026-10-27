<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory()->selling(),
            'type' => 'product',
            'slug' => fake()->unique()->slug(3),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(10),
            'price' => 1000000,
            'stock' => null,
            'is_active' => true,
        ];
    }

    /**
     * A service: booked for a date, no stock.
     */
    public function service(?string $unit = null): static
    {
        return $this->state(fn () => ['type' => 'service', 'price_unit' => $unit, 'stock' => null]);
    }
}
