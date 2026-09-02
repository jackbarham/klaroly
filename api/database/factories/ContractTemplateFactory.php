<?php

namespace Database\Factories;

use App\Models\ContractTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractTemplate>
 */
class ContractTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'market' => 'GB',
            'vertical' => 'wedding_makeup',
            'version' => 1,
            'name' => 'Standard agreement',
            'body' => "Agreement between {{business_name}} and {{contact_name}}.\n\nTotal: {{total}}. Deposit: {{deposit}}.",
            'effective_from' => today(),
        ];
    }

    public function system(): static
    {
        return $this->state(fn (array $attributes) => ['account_id' => null]);
    }
}
