<?php

use App\Enums\EventType;
use App\Enums\LocationType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Two models of time, on purpose.
 *
 * An event is stored as local wall clock plus an IANA timezone name: event_date,
 * start_time and timezone. A wedding at 2pm in Manchester is 14:00 plus
 * Europe/London, and it stays 14:00 whatever the server's clock or the
 * artist's phone is set to. The clash warning compares event_date values, not
 * instants, so two jobs on the same calendar day warn each other even when
 * they are in different countries.
 *
 * Everything that fires is the opposite: a reminder is computed to a UTC
 * instant (timestamptz) when it is scheduled and recomputed if the event
 * moves. Audit, signature and financial timestamps are UTC without exception.
 * The two are different because they answer different questions: "when is
 * the job" is a human fact about a place, "when does this run" is a machine
 * fact about a moment.
 */
return new class extends Migration
{
    /**
     * Anything on a booking that touches a date. Normally two rows per
     * booking. At most one main event, enforced by a partial unique index.
     */
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('label', 60)->nullable();
            $table->date('event_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->time('ready_by_time')->nullable();
            $table->string('timezone', 64)->default('Europe/London');
            $table->string('location_type', 10)->nullable();
            $table->string('address_line_1', 120)->nullable();
            $table->string('address_line_2', 120)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('postcode', 12)->nullable();
            $table->char('country', 2)->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->string('venue_name', 120)->nullable();
            $table->text('venue_address')->nullable();
            $table->integer('travel_distance_m')->nullable();
            $table->integer('travel_duration_s')->nullable();
            $table->timestampTz('travel_estimated_at')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestampsTz();

            $table->index(['account_id', 'event_date']);
            $table->index(['account_id', 'type', 'event_date']);
            $table->index('booking_id');
        });

        DB::statement(EventType::checkConstraintSql('events', 'type'));
        DB::statement(LocationType::checkConstraintSql('events', 'location_type'));
        DB::statement("create unique index events_booking_id_main_unique on events (booking_id) where type = 'main'");
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
