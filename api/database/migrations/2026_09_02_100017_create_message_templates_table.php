<?php

use App\Enums\TemplateKey;
use App\Enums\TemplateMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One table, three tiers. account_id null is a system default, account_id
     * set is the artist's override, booking_id set is a per-booking override.
     * Resolution is booking, then account, then system.
     */
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->string('locale', 10)->default('en-GB');
            $table->string('vertical', 40)->nullable();
            $table->string('name', 80);
            $table->string('subject', 200);
            $table->text('body');
            $table->jsonb('variants')->nullable();
            $table->boolean('enabled')->default(true);
            $table->string('mode', 10)->default(TemplateMode::Copy->value);
            $table->jsonb('trigger')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestampsTz();
        });

        DB::statement(TemplateKey::checkConstraintSql('message_templates', 'key'));
        DB::statement(TemplateMode::checkConstraintSql('message_templates', 'mode'));

        // Nulls are treated as equal so that there can be only one system
        // default per key and locale, not one per row that happens to be null.
        DB::statement('alter table message_templates add constraint message_templates_tier_key_unique unique nulls not distinct (account_id, booking_id, key, locale)');
        DB::statement('alter table message_templates add constraint message_templates_booking_id_check check (booking_id is null or account_id is not null)');
    }

    public function down(): void
    {
        Schema::dropIfExists('message_templates');
    }
};
