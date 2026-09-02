<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The dated stream. One row per note, private, never merged into a
     * template. A note belongs to a booking, a contact, or both.
     */
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestampTz('remind_at')->nullable();
            $table->timestampTz('reminded_at')->nullable();
            $table->timestampsTz();

            $table->index(['booking_id', 'created_at']);
        });

        DB::statement('alter table notes add constraint notes_subject_check check (booking_id is not null or contact_id is not null)');
        DB::statement('create index notes_account_id_remind_at_index on notes (account_id, remind_at) where remind_at is not null');
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
