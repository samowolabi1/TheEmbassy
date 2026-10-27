<?php

namespace Database\Factories;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'RT-'.strtoupper(fake()->unique()->bothify('????????########')),
            'email' => fake()->safeEmail(),
            'amount' => 500000,
            'platform_fee' => 0,
            'currency' => 'NGN',
        ];
    }
}
