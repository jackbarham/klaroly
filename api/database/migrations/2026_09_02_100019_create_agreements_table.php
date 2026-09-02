<?php

use App\Enums\AgreementStatus;
use App\Enums\SignedMethod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per version per booking. A signed agreement is never edited.
     * The rendered body is the exact text, not template plus data. The
     * self-referencing foreign key on superseded_by_id is added in the final
     * migration of the set.
     */
    public function up(): void
    {
        Schema::create('agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_template_id')->nullable()->constrained()->nullOnDelete();
            $table->smallInteger('version');
            $table->string('status', 12)->default(AgreementStatus::Draft->value);
            $table->text('rendered_body');
            $table->char('rendered_sha256', 64);
            $table->string('pdf_path', 255)->nullable();
            $table->bigInteger('total_minor');
            $table->bigInteger('deposit_minor')->default(0);
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('first_viewed_at')->nullable();
            $table->timestampTz('signed_at')->nullable();
            $table->string('signed_method', 10)->nullable();
            $table->string('signed_name', 120)->nullable();
            $table->ipAddress('signed_ip')->nullable();
            $table->text('signed_user_agent')->nullable();
            $table->string('signed_note', 255)->nullable();
            $table->unsignedBigInteger('superseded_by_id')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['booking_id', 'version']);
            $table->index(['account_id', 'status']);
        });

        DB::statement(AgreementStatus::checkConstraintSql('agreements', 'status'));
        DB::statement(SignedMethod::checkConstraintSql('agreements', 'signed_method'));
    }

    public function down(): void
    {
        Schema::dropIfExists('agreements');
    }
};
