<?php

use App\Enums\DepositType;
use App\Enums\TravelCharging;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per account, created with it. Everything the artist sets once
     * and may override per booking.
     */
    public function up(): void
    {
        Schema::create('account_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->unique()->constrained()->cascadeOnDelete();
            $table->jsonb('features')->default('{}');
            $table->string('deposit_type', 10)->default(DepositType::Percent->value);
            $table->bigInteger('deposit_amount_minor')->nullable();
            $table->smallInteger('deposit_percent')->nullable();
            $table->smallInteger('deposit_due_days')->default(7);
            $table->smallInteger('balance_due_days_before')->default(28);
            $table->text('payment_instructions')->nullable();
            $table->string('invoice_prefix', 10)->default('INV');
            $table->integer('next_invoice_number')->default(1);
            $table->string('legal_name', 160)->nullable();
            $table->string('address_line_1', 120)->nullable();
            $table->string('address_line_2', 120)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('postcode', 12)->nullable();
            $table->string('tax_number', 30)->nullable();
            $table->string('base_postcode', 12)->nullable();
            $table->string('travel_charging', 10)->default(TravelCharging::Included->value);
            $table->smallInteger('travel_free_radius_miles')->nullable();
            $table->bigInteger('travel_rate_per_mile_minor')->nullable()->default(45);
            $table->bigInteger('travel_flat_fee_minor')->nullable();
            $table->time('early_start_before')->nullable();
            $table->smallInteger('business_year_start_month')->default(4);
            $table->smallInteger('business_year_start_day')->default(6);
            $table->timestampsTz();
        });

        DB::statement(DepositType::checkConstraintSql('account_settings', 'deposit_type'));
        DB::statement(TravelCharging::checkConstraintSql('account_settings', 'travel_charging'));

        // A fixed deposit needs an amount and a percentage deposit needs a
        // percentage. The unused column may be null or not; only the one in
        // use is required.
        DB::statement("alter table account_settings add constraint account_settings_deposit_rule_check check ((deposit_type = 'fixed' and deposit_amount_minor is not null) or (deposit_type = 'percent' and deposit_percent is not null))");
    }

    public function down(): void
    {
        Schema::dropIfExists('account_settings');
    }
};
