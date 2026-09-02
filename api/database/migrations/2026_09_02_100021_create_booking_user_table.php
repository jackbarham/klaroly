<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which collaborators are on which booking. Empty in the first build.
     * Permissions live on account_user; this table only answers "is this
     * person on this job".
     */
    public function up(): void
    {
        Schema::create('booking_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('added_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['booking_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_user');
    }
};
