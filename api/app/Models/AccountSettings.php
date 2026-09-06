<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\DepositType;
use App\Enums\TravelCharging;
use App\Models\Concerns\BelongsToAccount;
use Database\Factories\AccountSettingsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per account. Everything the artist sets once and may override per
 * booking. The features column is read only through App\Services\Features.
 */
#[Fillable([
    'account_id', 'features', 'deposit_type', 'deposit_amount_minor', 'deposit_percent', 'deposit_due_days',
    'balance_due_days_before', 'hold_days', 'payment_instructions', 'invoice_prefix', 'next_invoice_number', 'legal_name',
    'address_line_1', 'address_line_2', 'city', 'postcode', 'tax_number', 'base_postcode', 'travel_charging',
    'travel_free_radius_miles', 'travel_rate_per_mile_minor', 'travel_flat_fee_minor', 'early_start_before',
    'business_year_start_month', 'business_year_start_day',
])]
class AccountSettings extends Model
{
    /** @use HasFactory<AccountSettingsFactory> */
    use BelongsToAccount, HasFactory;

    protected $table = 'account_settings';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'features' => 'array',
            'deposit_type' => DepositType::class,
            'deposit_amount_minor' => MoneyCast::class,
            'travel_charging' => TravelCharging::class,
            'travel_rate_per_mile_minor' => MoneyCast::class,
            'travel_flat_fee_minor' => MoneyCast::class,
        ];
    }
}
