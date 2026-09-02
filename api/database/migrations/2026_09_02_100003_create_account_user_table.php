<?php

use App\Enums\AccountRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Membership. One owner per account, any number of collaborators later.
     * Whether a collaborator is an assistant or a second artist is a matter of
     * which toggles are on, not a different role.
     */
    public function up(): void
    {
        Schema::create('account_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->boolean('can_edit')->default(false);
            $table->boolean('can_see_prices')->default(false);
            $table->boolean('can_see_invoices')->default(false);
            $table->boolean('can_see_contacts')->default(true);
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('invited_at')->nullable();
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampsTz();

            $table->unique(['account_id', 'user_id']);
        });

        DB::statement(AccountRole::checkConstraintSql('account_user', 'role'));
        DB::statement("create unique index account_user_owner_unique on account_user (account_id) where role = 'owner'");
    }

    public function down(): void
    {
        Schema::dropIfExists('account_user');
    }
};
