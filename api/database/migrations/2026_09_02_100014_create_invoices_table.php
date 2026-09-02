<?php

use App\Enums\InvoiceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One per booking by default, numbered at issue, carrying the deposit and
     * both due dates. Drafts have no number, so there are no gaps. Paid state
     * is never stored; it is derived from payments.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('status', 10)->default(InvoiceStatus::Draft->value);
            $table->integer('sequence')->nullable();
            $table->string('number', 24)->nullable();
            $table->char('currency', 3);
            $table->date('issued_on')->nullable();
            $table->jsonb('lines');
            $table->bigInteger('subtotal_minor');
            $table->bigInteger('discount_minor')->default(0);
            $table->bigInteger('total_minor');
            $table->bigInteger('deposit_minor')->default(0);
            $table->date('deposit_due_on')->nullable();
            $table->date('balance_due_on')->nullable();
            $table->text('payment_instructions')->nullable();
            $table->text('notes')->nullable();
            $table->date('reminders_snoozed_until')->nullable();
            $table->string('pdf_path', 255)->nullable();
            $table->timestampTz('voided_at')->nullable();
            $table->string('void_reason', 200)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['account_id', 'sequence']);
            $table->unique(['account_id', 'number']);
            $table->index(['account_id', 'status', 'balance_due_on']);
            $table->index(['account_id', 'status', 'deposit_due_on']);
            $table->index('booking_id');
        });

        DB::statement(InvoiceStatus::checkConstraintSql('invoices', 'status'));
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
