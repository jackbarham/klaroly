<?php

use App\Enums\BookingContactRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Everyone else on the day who is not the paying contact: partner,
     * planner, venue coordinator, emergency contact.
     */
    public function up(): void
    {
        Schema::create('booking_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('role', 30);
            $table->string('name', 120);
            $table->string('email', 255)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('note', 255)->nullable();
            $table->timestampsTz();

            $table->index('booking_id');
        });

        DB::statement(BookingContactRole::checkConstraintSql('booking_contacts', 'role'));
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_contacts');
    }
};
