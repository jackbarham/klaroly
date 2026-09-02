<?php

use App\Enums\PaymentMethod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rows, never a boolean. Negative amounts are refunds. booking_id is
     * redundant with invoice_id on purpose, so "what has this booking paid"
     * needs no join. Hard-deleted on a misclick.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('amount_minor');
            $table->date('paid_on');
            $table->string('method', 20)->default(PaymentMethod::BankTransfer->value);
            $table->string('reference', 80)->nullable();
            $table->string('note', 255)->nullable();
            $table->string('external_id', 255)->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index('invoice_id');
            $table->index(['account_id', 'paid_on']);
        });

        DB::statement(PaymentMethod::checkConstraintSql('payments', 'method'));
        DB::statement('alter table payments add constraint payments_amount_minor_check check (amount_minor <> 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
