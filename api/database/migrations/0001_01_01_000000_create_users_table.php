<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A user is a person with a login and nothing more. Being an artist is a
     * row in account_user; being a client is contacts.user_id. The foreign key
     * on last_account_id is added in the final migration of the set, because
     * accounts is created after users.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 120);
            $table->string('email', 255);
            $table->string('password', 255)->nullable();
            $table->timestampTz('email_verified_at')->nullable();
            $table->rememberToken();
            $table->jsonb('notification_preferences')->default('{}');
            $table->timestampTz('marketing_consent_at')->nullable();
            $table->string('marketing_consent_source', 40)->nullable();
            $table->unsignedBigInteger('last_account_id')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        // Email is unique regardless of case, so the unique index is on the
        // lowercased value rather than on the column itself.
        DB::statement('create unique index users_email_lower_unique on users (lower(email))');

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
