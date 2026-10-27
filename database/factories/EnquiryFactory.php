<?php

namespace Database\Factories;

use App\Models\Enquiry;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enquiry>
 */
class EnquiryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'shop_id' => fn (array $attributes) => Product::find($attributes['product_id'])->shop_id,
            'name' => fake()->name(),
            'phone' => '0803 111 2222',
            'email' => fake()->safeEmail(),
            'message' => fake()->sentence(12),
        ];
    }
}
