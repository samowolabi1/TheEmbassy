<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->name(),
            'phone' => '0803 '.fake()->numerify('### ####'),
            'email' => fake()->unique()->safeEmail(),
        ];
    }

    /**
     * Approved.
     */
    public function active(): static
    {
        return $this->state(fn () => [
            'status' => Member::ACTIVE,
            'joined_on' => today()->subYear(),
        ]);
    }

    /**
     * On the register only, without a login.
     */
    public function withoutLogin(): static
    {
        return $this->state(fn () => ['user_id' => null]);
    }
}
