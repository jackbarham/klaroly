<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Versioned agreement wording, by market and vertical. account_id null is
     * the system default. Editing creates a new version row; the previous one
     * is retired, not changed, so a signed agreement can always point at the
     * exact wording it was built from.
     */
    public function up(): void
    {
        Schema::create('contract_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->char('market', 2);
            $table->string('vertical', 40);
            $table->integer('version');
            $table->string('name', 80);
            $table->text('body');
            $table->date('effective_from');
            $table->date('retired_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement('alter table contract_templates add constraint contract_templates_version_unique unique nulls not distinct (account_id, market, vertical, version)');
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_templates');
    }
};
