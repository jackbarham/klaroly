<?php

use App\Enums\EntitlementSource;
use App\Enums\EntitlementStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who is allowed in, separately from who paid. Populated from billing
     * webhooks later; manual rows let an account in without a store object.
     */
    public function up(): void
    {
        Schema::create('entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('source', 10);
            $table->string('external_id', 255)->nullable();
            $table->string('plan_key', 40)->nullable();
            $table->string('status', 12);
            $table->timestampTz('current_period_end')->nullable();
            $table->timestampsTz();

            $table->index(['account_id', 'status']);
        });

        DB::statement(EntitlementSource::checkConstraintSql('entitlements', 'source'));
        DB::statement(EntitlementStatus::checkConstraintSql('entitlements', 'status'));
    }

    public function down(): void
    {
        Schema::dropIfExists('entitlements');
    }
};
