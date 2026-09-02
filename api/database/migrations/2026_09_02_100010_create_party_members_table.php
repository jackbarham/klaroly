<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who is being served, each with a service from the rate card. The
     * service name is snapshotted so a deleted rate card row does not blank
     * the party list. No ages, no dates of birth.
     */
    public function up(): void
    {
        Schema::create('party_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120)->nullable();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service_name', 80)->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestampsTz();

            $table->index('booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_members');
    }
};
