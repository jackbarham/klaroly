<?php

use App\Enums\ServiceAppliesTo;
use App\Enums\ServiceKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The rate card. Rows, never an enum. Seeded per vertical, all editable,
     * all deletable. Names are unique per account among rows that have not
     * been soft-deleted, so a deleted name can be reused.
     */
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('description', 255)->nullable();
            $table->string('kind', 10)->default(ServiceKind::Service->value);
            $table->string('applies_to', 10)->default(ServiceAppliesTo::Both->value);
            $table->bigInteger('price_minor')->default(0);
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        DB::statement(ServiceKind::checkConstraintSql('services', 'kind'));
        DB::statement(ServiceAppliesTo::checkConstraintSql('services', 'applies_to'));
        DB::statement('create unique index services_account_id_name_unique on services (account_id, name) where deleted_at is null');
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
