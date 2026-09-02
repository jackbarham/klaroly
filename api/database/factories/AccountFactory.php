<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\AccountSettings;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();

        return [
            'name' => $firstName.' '.$lastName.' Makeup',
            'username' => strtolower(preg_replace('/[^a-z0-9]/i', '', $firstName.$lastName).fake()->unique()->numberBetween(10, 9999)),
            'vertical' => 'wedding_makeup',
            'country' => 'GB',
            'locale' => 'en-GB',
            'currency' => 'GBP',
            'timezone' => 'Europe/London',
            'profile_enabled' => false,
        ];
    }

    /**
     * An account with its settings row, as every real account has.
     */
    public function withSettings(array $settings = []): static
    {
        return $this->has(AccountSettings::factory()->state($settings), 'settings');
    }

    public function onTrial(): static
    {
        return $this->state(fn (array $attributes) => [
            'trial_ends_at' => now()->addDays(30),
        ]);
    }
}
