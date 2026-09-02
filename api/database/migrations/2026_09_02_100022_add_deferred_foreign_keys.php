<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foreign keys that could not be declared when their table was created,
     * either because the target table did not exist yet or because the key
     * points back at its own table.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('last_account_id')->references('id')->on('accounts')->nullOnDelete();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreign('source_booking_id')->references('id')->on('bookings')->nullOnDelete();
        });

        Schema::table('agreements', function (Blueprint $table) {
            $table->foreign('superseded_by_id')->references('id')->on('agreements')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->dropForeign(['superseded_by_id']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['source_booking_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['last_account_id']);
        });
    }
};
