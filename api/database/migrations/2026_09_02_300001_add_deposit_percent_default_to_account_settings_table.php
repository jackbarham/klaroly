<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * deposit_type defaults to "percent" and the deposit rule check insists
     * on a percentage when it does, so a bare insert used to fail. A default
     * of 25 makes the table's own defaults pass its own check (decision 90).
     */
    public function up(): void
    {
        DB::statement('alter table account_settings alter column deposit_percent set default 25');
    }

    public function down(): void
    {
        DB::statement('alter table account_settings alter column deposit_percent drop default');
    }
};
