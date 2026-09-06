<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How long a date is held once an artist pencils it in, in days.
     *
     * A column rather than a config entry, which is the opposite call from
     * decision 218's three entries in config/bookings.php, and the difference
     * is that this one's shape is settled even though the number is not. It
     * describes the business rather than the app: more than one screen reads
     * it, and a London artist holding a date for a week and a rural one holding
     * it for three are both working correctly. cold_enquiry_days is a threshold
     * on the app's own nagging with no settled home, intake_available is a fact
     * about whether a table exists, and the three caps are endpoint costs; none
     * of the four is per-account by design.
     *
     * It is also structurally identical to the two siblings it sits beside,
     * deposit_due_days and balance_due_days_before: a smallint count of days on
     * the same row, read at the same kind of moment. Neither of those waited
     * for a settings screen and this does not either, which is the standing
     * rule that settled columns go in and the UI follows.
     *
     * **Fourteen days, and the demo fixtures had already assumed it.** Two
     * weeks is the courtesy hold this trade runs on: long enough that a client
     * who means it will have replied, short enough that a summer Saturday is
     * not quietly gone for a month. The seeder's two hand-set holds were
     * converted_at plus ten and minus five against conversions four and twenty
     * days old, which is a fourteen-day hold written out by hand.
     *
     * It is deliberately NOT cold_enquiry_days, which is 21, and the two must
     * not be merged for tidiness: one asks how long a date is being held, the
     * other how long a conversation has been silent.
     */
    public function up(): void
    {
        Schema::table('account_settings', function (Blueprint $table) {
            $table->smallInteger('hold_days')->default(14);
        });
    }

    public function down(): void
    {
        Schema::table('account_settings', function (Blueprint $table) {
            $table->dropColumn('hold_days');
        });
    }
};
