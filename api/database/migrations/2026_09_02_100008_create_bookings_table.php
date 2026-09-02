<?php

use App\Enums\BookingSource;
use App\Enums\BookingStage;
use App\Enums\DiscountType;
use App\Enums\PhotoConsent;
use App\Enums\PricingMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One table from first enquiry to closed. Every stage lives here and the
     * interface shows enquiries and bookings as two lists filtered on stage.
     * Totals are never stored; App\Services\BookingPricing computes them.
     * The self-referencing foreign key on source_booking_id is added in the
     * final migration of the set.
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->restrictOnDelete();
            $table->string('stage', 20)->default(BookingStage::New->value);
            $table->string('source', 30)->nullable();
            $table->unsignedBigInteger('source_booking_id')->nullable();
            $table->text('enquiry_message')->nullable();
            $table->string('lost_reason', 40)->nullable();
            $table->timestampTz('lost_at')->nullable();
            $table->timestampTz('converted_at')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->date('hold_expires_at')->nullable();
            $table->timestampTz('last_touched_at');
            $table->char('currency', 3);
            $table->string('pricing_mode', 10)->default(PricingMode::Itemised->value);
            $table->bigInteger('fixed_price_minor')->nullable();
            $table->string('fixed_price_description', 200)->nullable();
            $table->string('discount_type', 10)->nullable();
            $table->integer('discount_value')->nullable();
            $table->string('discount_reason', 120)->nullable();
            $table->bigInteger('deposit_override_minor')->nullable();
            $table->smallInteger('deposit_override_percent')->nullable();
            $table->string('photo_consent', 20)->nullable();
            $table->timestampTz('photo_consent_recorded_at')->nullable();
            $table->string('gallery_url', 500)->nullable();
            $table->date('gallery_received_on')->nullable();
            $table->string('access_pin', 255)->nullable();
            $table->timestampTz('access_pin_changed_at')->nullable();
            $table->jsonb('feature_overrides')->default('{}');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['account_id', 'stage']);
            $table->index(['account_id', 'last_touched_at']);
            $table->index(['account_id', 'contact_id']);
            $table->index('source_booking_id');
        });

        DB::statement(BookingStage::checkConstraintSql('bookings', 'stage'));
        DB::statement(BookingSource::checkConstraintSql('bookings', 'source'));
        DB::statement(PricingMode::checkConstraintSql('bookings', 'pricing_mode'));
        DB::statement(DiscountType::checkConstraintSql('bookings', 'discount_type'));
        DB::statement(PhotoConsent::checkConstraintSql('bookings', 'photo_consent'));
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
