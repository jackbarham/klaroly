<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The business. The tenant. Every customer-data table hangs off this one.
     * The four Cashier columns live here because the account, not the user,
     * is the billable model.
     */
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('username', 63)->unique();
            $table->string('vertical', 40)->default('wedding_makeup');
            $table->char('country', 2)->default('GB');
            $table->string('locale', 10)->default('en-GB');
            $table->char('currency', 3)->default('GBP');
            $table->string('timezone', 64)->default('Europe/London');
            $table->boolean('profile_enabled')->default(false);
            $table->string('stripe_id', 255)->nullable()->unique();
            $table->string('pm_type', 255)->nullable();
            $table->string('pm_last_four', 4)->nullable();
            $table->timestampTz('trial_ends_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        // The same regex App\Rules\Username enforces, so a bad value cannot
        // reach the table by any other route.
        DB::statement("alter table accounts add constraint accounts_username_check check (username ~ '^[a-z][a-z0-9]{2,62}$')");
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
