<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The person who books and pays. Name, email, phone, address and nothing
     * else. No dates of birth anywhere in the schema, for anyone.
     */
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('first_name', 80);
            $table->string('last_name', 80)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address_line_1', 120)->nullable();
            $table->string('address_line_2', 120)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('postcode', 12)->nullable();
            $table->char('country', 2)->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['account_id', 'last_name', 'first_name']);
            $table->index(['account_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
