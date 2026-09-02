<?php

namespace Database\Factories;

use App\Enums\EntitlementSource;
use App\Enums\EntitlementStatus;
use App\Models\Entitlement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Entitlement>
 */
class EntitlementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => EntitlementSource::Manual,
            'plan_key' => 'early_access_monthly',
            'status' => EntitlementStatus::Active,
            'current_period_end' => now()->addMonth(),
        ];
    }

    public function trialing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EntitlementStatus::Trialing,
            'current_period_end' => now()->addDays(30),
        ]);
    }
}
