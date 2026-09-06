<?php

use App\Enums\BookingStage;
use App\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Every UTC instant this application stores comes back as the instant it
 * stored.
 *
 * That sounds like it needs no test, and it was false. Eloquent writes a
 * datetime as 'Y-m-d H:i:s' with no offset, and Postgres reads a naked
 * timestamp into a timestamptz column using the session's own timezone, which
 * it inherits from the server unless it is told otherwise. On a machine
 * defaulting to Europe/London that put every instant in an hour early for the
 * eight months the clocks are forward: now() at 18:57 UTC was stored as 17:57
 * UTC and read back as 17:57.
 *
 * **Nothing caught it because everything wrote and read through the same
 * shift**, so every ordering, every "is this overdue" and every relative
 * comparison in the suite still held. It shows only when a stored value is
 * compared against an in-memory now(), which is why the clock assertion in
 * EnquiryUpdateTest found it and four years of timestamps would not have.
 *
 * The fix is 'timezone' => 'UTC' on the pgsql connection in config/database.php.
 * These are here so it cannot be removed quietly, and so that a machine or a
 * managed database with a different server default fails here rather than in
 * an audit trail.
 */
it('keeps the session timezone at UTC rather than inheriting the server\'s', function () {
    expect(DB::select('show timezone')[0]->TimeZone)->toBe('UTC');
});

it('reads a timestamptz back as the instant it was written', function () {
    $moment = now()->startOfSecond();

    DB::statement('create temporary table timezone_check (at timestamptz)');
    DB::table('timezone_check')->insert(['at' => $moment]);

    $read = CarbonImmutable::parse(DB::table('timezone_check')->first()->at);

    expect($read->equalTo($moment))->toBeTrue();
});

/**
 * The same thing through a model and a cast, which is the path the application
 * actually uses. Two of them are the columns the enquiries list is ordered by
 * and the waiting-on axis reads, so an hour here is an hour on every figure
 * that screen shows.
 */
it('round-trips a booking\'s own instants through the cast', function () {
    actingForAccount();

    $moment = now()->startOfSecond();

    $booking = Booking::factory()->create([
        'stage' => BookingStage::Lost,
        'last_touched_at' => $moment,
        'lost_at' => $moment,
    ]);

    $fresh = $booking->fresh();

    expect($fresh->last_touched_at->equalTo($moment))->toBeTrue()
        ->and($fresh->lost_at->equalTo($moment))->toBeTrue();
});
