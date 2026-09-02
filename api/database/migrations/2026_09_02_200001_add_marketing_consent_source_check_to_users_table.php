<?php

use App\Enums\MarketingConsentSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The users table was created before the consent-source values were
     * settled, so its check constraint arrives here, generated from the enum
     * like every other one. Null still passes: consent that was never given
     * has no source.
     */
    public function up(): void
    {
        DB::statement(MarketingConsentSource::checkConstraintSql('users', 'marketing_consent_source'));
    }

    public function down(): void
    {
        DB::statement('alter table users drop constraint users_marketing_consent_source_check');
    }
};
