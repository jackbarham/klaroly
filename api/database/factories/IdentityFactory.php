<?php

namespace Database\Factories;

use App\Enums\IdentityProvider;
use App\Models\Identity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Identity>
 */
class IdentityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => fake()->randomElement(IdentityProvider::cases()),
            'provider_user_id' => fake()->unique()->uuid(),
            'provider_email' => fake()->safeEmail(),
            'email_is_private' => false,
        ];
    }
}
