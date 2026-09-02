<?php

namespace Database\Factories;

use App\Enums\ServiceAppliesTo;
use App\Enums\ServiceKind;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'kind' => ServiceKind::Service,
            'applies_to' => ServiceAppliesTo::Both,
            'price_minor' => fake()->numberBetween(4, 30) * 500,
            'sort_order' => 0,
            'active' => true,
        ];
    }

    public function expense(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => ServiceKind::Expense,
            'price_minor' => 0,
        ]);
    }

    public function travel(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => ServiceKind::Travel,
            'price_minor' => 0,
        ]);
    }
}
