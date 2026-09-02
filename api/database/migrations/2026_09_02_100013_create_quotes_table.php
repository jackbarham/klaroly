<?php

use App\Enums\PricingMode;
use App\Enums\QuoteSentVia;
use App\Enums\QuoteStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An immutable record of a written quote that was copied, sent or
     * downloaded, with an outcome. Copying to the clipboard creates a row,
     * because that is the moment a number reached the client.
     */
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('number');
            $table->char('currency', 3);
            $table->string('pricing_mode', 10);
            $table->jsonb('lines');
            $table->bigInteger('subtotal_minor');
            $table->bigInteger('discount_minor')->default(0);
            $table->bigInteger('total_minor');
            $table->bigInteger('deposit_minor')->nullable();
            $table->text('rendered_text');
            $table->string('status', 10)->default(QuoteStatus::Sent->value);
            $table->timestampTz('sent_at');
            $table->string('sent_via', 10)->default(QuoteSentVia::Copy->value);
            $table->timestampTz('responded_at')->nullable();
            $table->date('valid_until')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['booking_id', 'number']);
            $table->index(['account_id', 'status']);
        });

        DB::statement(PricingMode::checkConstraintSql('quotes', 'pricing_mode'));
        DB::statement(QuoteStatus::checkConstraintSql('quotes', 'status'));
        DB::statement(QuoteSentVia::checkConstraintSql('quotes', 'sent_via'));
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
