<?php

namespace Database\Factories;

use App\Enums\AccountRole;
use App\Models\AccountUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountUser>
 */
class AccountUserFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'role' => AccountRole::Collaborator,
            'can_edit' => false,
            'can_see_prices' => false,
            'can_see_invoices' => false,
            'can_see_contacts' => true,
            'accepted_at' => now(),
        ];
    }

    public function owner(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => AccountRole::Owner,
            'can_edit' => true,
            'can_see_prices' => true,
            'can_see_invoices' => true,
            'can_see_contacts' => true,
        ]);
    }
}
