<?php

use App\Enums\MarketingConsentSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The settings screen can now record a consent, so the enum has a fourth
     * case and the check constraint has to widen to match. Widening one is
     * always drop and re-add, generated from the enum, which is why the
     * constraint carries a predictable <table>_<column>_check name.
     */
    public function up(): void
    {
        DB::statement('alter table users drop constraint users_marketing_consent_source_check');

        DB::statement(MarketingConsentSource::checkConstraintSql('users', 'marketing_consent_source'));
    }

    /**
     * The enum already holds the fourth case at this point, so the narrower
     * constraint is written out rather than generated. Any row already
     * recording a settings consent would block the rollback, which is the
     * honest answer: the value would no longer be legal.
     */
    public function down(): void
    {
        DB::statement('alter table users drop constraint users_marketing_consent_source_check');

        DB::statement("alter table users add constraint users_marketing_consent_source_check check (marketing_consent_source in ('portal', 'app_signup', 'other'))");
    }
};
