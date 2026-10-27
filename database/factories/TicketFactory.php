<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_order_id' => TicketOrder::factory(),
            'ticket_type_id' => TicketType::factory(),
            'code' => strtoupper(Str::random(10)),
        ];
    }
}
