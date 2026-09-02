<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\UsernameHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsernameHistory>
 */
class UsernameHistoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'username' => strtolower(fake()->unique()->lexify('??????')),
            'claimed_at' => now()->subMonths(6),
            'released_at' => null,
        ];
    }

    public function released(): static
    {
        return $this->state(fn (array $attributes) => [
            'released_at' => now()->subMonth(),
        ]);
    }
}
