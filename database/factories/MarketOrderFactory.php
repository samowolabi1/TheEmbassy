<?php

namespace Database\Factories;

use App\Models\MarketOrder;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MarketOrder>
 */
class MarketOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $product = Product::factory()->create();

        return [
            'shop_id' => $product->shop_id,
            'product_id' => $product->id,
            'code' => strtoupper(Str::random(8)),
            'quantity' => 1,
            'unit_price' => $product->price,
            'total' => $product->price,
            'commission' => 0,
            'platform_fee' => 0,
            'buyer_name' => fake()->name(),
            'buyer_email' => fake()->safeEmail(),
            'buyer_phone' => '0812 000 0000',
            'delivery_note' => 'Collect at church',
        ];
    }
}
