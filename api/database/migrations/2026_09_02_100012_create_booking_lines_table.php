<?php

use App\Enums\LineKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The live, editable price on the booking. Description and unit price are
     * snapshotted the moment a line is added. The line total is
     * quantity * unit_price_minor and is never stored.
     */
    public function up(): void
    {
        Schema::create('booking_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 10)->default(LineKind::Service->value);
            $table->string('description', 120);
            $table->smallInteger('quantity')->default(1);
            $table->bigInteger('unit_price_minor');
            $table->smallInteger('sort_order')->default(0);
            $table->timestampsTz();

            $table->index('booking_id');
        });

        DB::statement(LineKind::checkConstraintSql('booking_lines', 'kind'));
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_lines');
    }
};
