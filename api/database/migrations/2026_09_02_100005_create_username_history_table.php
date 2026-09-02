<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every username an account has ever held, including the current one.
     * The unique constraint across all history is what makes a released name
     * unreclaimable by anyone else. No updated_at: rows are written, released
     * and never otherwise changed.
     */
    public function up(): void
    {
        Schema::create('username_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('username', 63)->unique();
            $table->timestampTz('claimed_at');
            $table->timestampTz('released_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('username_history');
    }
};
