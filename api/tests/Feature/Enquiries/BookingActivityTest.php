<?php

use App\Enums\BookingStage;
use App\Models\Booking;

/**
 * Booking::touchActivity(), on its own and away from the route that calls it.
 *
 * It is tested separately because it is a rule rather than a detail of one
 * endpoint. `last_touched_at` is what the enquiries list is ordered by and what
 * the cold branch of App\Services\WaitingOnResolver reads, so a write path
 * that does not call this is not a bug in itself: it is a bug in the enquiries
 * list and in the Home attention block, arriving somewhere neither of them can
 * see. Notes, messages, price changes and the intake form are all still to
 * come.
 */
it('sets last touched to now and saves it', function () {
    actingForAccount();

    $booking = Booking::factory()->possible()->create([
        'last_touched_at' => now()->subDays(30),
    ]);

    $moment = now()->addHours(2)->startOfSecond();

    $this->travelTo($moment, fn () => $booking->touchActivity());

    // Against a named instant rather than "not what it was". A method that set
    // the column to the epoch, to created_at, or to a year hence would satisfy
    // "it changed", and every one of those is a broken enquiries list.
    expect($booking->fresh()->last_touched_at->equalTo($moment))->toBeTrue();
});

/**
 * It saves rather than only setting the column, which is Laravel's own touch()
 * semantics and what lets a caller write:
 *
 *     $booking->fill([...])->touchActivity();
 *
 * If it only set the attribute, that line would look right and persist
 * nothing.
 */
it('saves what the caller was already changing, in the same write', function () {
    actingForAccount();

    $booking = Booking::factory()->possible()->create();

    $booking->fill(['stage' => BookingStage::Quoted])->touchActivity();

    expect($booking->fresh()->stage)->toBe(BookingStage::Quoted);
});

// last_touched_at is not something a request may set, so the method forceFills
// it. Asserted because a booking is otherwise written through a Fillable
// attribute list, and a column left out of that list would make this a silent
// no-op rather than an error.
it('writes the column even though it is not fillable', function () {
    actingForAccount();

    $booking = Booking::factory()->possible()->create([
        'last_touched_at' => now()->subYear(),
    ]);

    $booking->touchActivity();

    expect($booking->fresh()->last_touched_at->isToday())->toBeTrue();
});
