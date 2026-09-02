<?php

namespace Database\Factories;

use App\Enums\DepositType;
use App\Enums\TravelCharging;
use App\Models\AccountSettings;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountSettings>
 */
class AccountSettingsFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'features' => [],
            'deposit_type' => DepositType::Percent,
            'deposit_percent' => 25,
            'deposit_due_days' => 7,
            'balance_due_days_before' => 28,
            'invoice_prefix' => 'INV',
            'next_invoice_number' => 1,
            'travel_charging' => TravelCharging::Included,
            'travel_rate_per_mile_minor' => 45,
            'business_year_start_month' => 4,
            'business_year_start_day' => 6,
        ];
    }

    public function fixedDeposit(int $amountMinor = 10000): static
    {
        return $this->state(fn (array $attributes) => [
            'deposit_type' => DepositType::Fixed,
            'deposit_amount_minor' => $amountMinor,
            'deposit_percent' => null,
        ]);
    }
}
