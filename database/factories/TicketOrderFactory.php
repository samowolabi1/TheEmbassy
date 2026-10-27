<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\TicketOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TicketOrder>
 */
class TicketOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'code' => strtoupper(Str::random(8)),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'total' => 0,
        ];
    }
}
