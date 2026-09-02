<?php

use App\Enums\IdentityProvider;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Third-party sign-in identities. Sits empty until Sign in with Apple and
     * Google arrive together. Provider sign-in never looks a user up by email.
     */
    public function up(): void
    {
        Schema::create('identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20);
            $table->string('provider_user_id', 255);
            $table->string('provider_email', 255)->nullable();
            $table->boolean('email_is_private')->default(false);
            $table->timestampsTz();

            $table->unique(['provider', 'provider_user_id']);
        });

        DB::statement(IdentityProvider::checkConstraintSql('identities', 'provider'));
    }

    public function down(): void
    {
        Schema::dropIfExists('identities');
    }
};
