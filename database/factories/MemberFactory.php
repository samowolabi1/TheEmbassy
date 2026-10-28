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
     * Approved, owing tokens from this month on the given plan.
     */
    public function active(?int $tokenPlanId = null): static
    {
        return $this->state(fn () => [
            'status' => Member::ACTIVE,
            'joined_on' => today()->subYear(),
            'token_plan_id' => $tokenPlanId,
            'tokens_from' => today()->startOfMonth(),
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
