<?php

use App\Enums\BookingStage;
use App\Models\Account;
use App\Models\Booking;
use App\Models\Event;

/*
 * bookings.hold_expires_at, and the writer that makes artist_not_held reachable.
 *
 * Business logic 5.1 starts the soft hold at Possible and 5.3 turns it into a
 * real one on converting. Until App\Services\SoftHold existed nothing in the
 * application wrote the column, so the first value in the waiting-on
 * precedence, above money because the date itself can be lost, could only fire
 * for rows a seeder set by hand.
 */

/**
 * Move an enquiry to a stage through the one route that writes stages.
 */
function moveTo(mixed $test, $user, Booking $booking, BookingStage $stage): Booking
{
    $test->actingAs($user)
        ->patchJson('/api/enquiries/'.$booking->id, ['stage' => $stage->value])
        ->assertOk();

    return $booking->fresh();
}

describe('starting a hold', function () {
    it('holds the date for the account\'s hold length when an enquiry reaches Possible', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $booking = enquiry(BookingStage::InConversation, today()->addMonths(8)->toDateString());

        currentAccount()->clear();

        expect($booking->hold_expires_at)->toBeNull();

        $moved = moveTo($this, $user, $booking, BookingStage::Possible);

        expect($moved->hold_expires_at->toDateString())
            ->toBe(today()->addDays(14)->toDateString());
    });

    /*
     * The write happens in the artist's own day. APP_TIMEZONE is UTC, so a hold
     * started late on a Tuesday evening in a zone ahead of UTC would otherwise
     * run from Wednesday and expire a day late.
     */
    it('counts from the account\'s own day rather than the application\'s', function () {
        $account = Account::factory()->withSettings()->create(['timezone' => 'Pacific/Auckland']);
        $user = createOwner([], $account);

        currentAccount()->set($account);
        $booking = enquiry(BookingStage::New, today()->addMonths(6)->toDateString());
        currentAccount()->clear();

        $moved = moveTo($this, $user, $booking, BookingStage::Possible);

        expect($moved->hold_expires_at->toDateString())
            ->toBe(now('Pacific/Auckland')->addDays(14)->toDateString());
    });

    it('uses the account\'s setting rather than the default when it has one', function () {
        $account = Account::factory()->withSettings(['hold_days' => 7])->create();
        $user = createOwner([], $account);

        currentAccount()->set($account);
        $booking = enquiry(BookingStage::New, today()->addMonths(4)->toDateString());
        currentAccount()->clear();

        expect(moveTo($this, $user, $booking, BookingStage::Possible)->hold_expires_at->toDateString())
            ->toBe(today()->addDays(7)->toDateString());
    });

    it('applies the default of fourteen to an account that has never set one', function () {
        // The migration's default, so a bare insert that names no hold length
        // still answers the same as one that does.
        $account = actingForAccount();

        expect($account->settings->hold_days)->toBe(14);
    });
});

describe('a hold that already exists', function () {
    /*
     * Both hold softly (5.1), so an artist who sends a quote has not
     * re-pencilled the date.
     */
    it('is not restarted by moving from Possible to Quoted', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $booking = enquiry(BookingStage::InConversation, today()->addMonths(9)->toDateString());
        currentAccount()->clear();

        $held = moveTo($this, $user, $booking, BookingStage::Possible)->hold_expires_at;

        // Six days later the artist sends a quote. The hold still runs out on
        // the day it always did.
        $this->travel(6)->days();

        expect(moveTo($this, $user, $booking, BookingStage::Quoted)->hold_expires_at->toDateString())
            ->toBe($held->toDateString());
    });

    /*
     * 5.3 says the soft hold becomes a real one, which is a change of kind, so
     * a new clock is the honest reading. Without it, converting a thirteen-day-
     * old soft hold would put "the date is not held" on the home screen the
     * next morning, about a booking the artist had just pencilled in.
     */
    it('is restarted by converting to Provisional', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $booking = enquiry(BookingStage::InConversation, today()->addMonths(7)->toDateString());
        currentAccount()->clear();

        $soft = moveTo($this, $user, $booking, BookingStage::Possible)->hold_expires_at;

        $this->travel(11)->days();

        $converted = moveTo($this, $user, $booking, BookingStage::Provisional);

        expect($converted->hold_expires_at->toDateString())
            ->toBe(today()->addDays(14)->toDateString())
            ->not->toBe($soft->toDateString())
            // converted_at is written in the same breath, so the two agree
            // about when the firm hold began.
            ->and($converted->converted_at->toDateString())->toBe(today()->toDateString());
    });

    /*
     * Asserted on the date rather than on "not null", because the question is
     * which of three things happens and two of them are non-null.
     */
    it('is left exactly as it was when a conversion is undone', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $booking = enquiry(BookingStage::Possible, today()->addMonths(5)->toDateString());
        currentAccount()->clear();

        $firm = moveTo($this, $user, $booking, BookingStage::Provisional)->hold_expires_at;
        expect($firm)->not->toBeNull();

        $this->travel(3)->days();

        $undone = moveTo($this, $user, $booking, BookingStage::Possible);

        // Not cleared, because the record still holds the date softly. Not
        // restarted, because that would let an artist extend a hold for ever by
        // converting and un-converting.
        expect($undone->hold_expires_at->toDateString())->toBe($firm->toDateString())
            ->and($undone->stage)->toBe(BookingStage::Possible)
            // The rest of what an undo undoes, so this is not asserting the
            // hold in isolation from the thing that moved.
            ->and($undone->converted_at)->toBeNull();
    });

    it('is not restarted by re-sending the same stage', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $booking = enquiry(BookingStage::New, today()->addMonths(3)->toDateString());
        currentAccount()->clear();

        $held = moveTo($this, $user, $booking, BookingStage::Possible)->hold_expires_at;

        $this->travel(4)->days();

        expect(moveTo($this, $user, $booking, BookingStage::Possible)->hold_expires_at->toDateString())
            ->toBe($held->toDateString());
    });

    /*
     * A policy change is not retroactive, and storing the expiry rather than
     * deriving it from converted_at plus the setting is what makes that true
     * for nothing. An artist who shortens her hold in March has not
     * retrospectively expired a date she pencilled in February.
     */
    it('is untouched when the setting changes, and the new length applies to the next write', function () {
        $account = Account::factory()->withSettings(['hold_days' => 14])->create();
        $user = createOwner([], $account);

        currentAccount()->set($account);
        $first = enquiry(BookingStage::New, today()->addMonths(8)->toDateString());
        $second = enquiry(BookingStage::New, today()->addMonths(9)->toDateString());
        currentAccount()->clear();

        $held = moveTo($this, $user, $first, BookingStage::Possible)->hold_expires_at;

        $account->settings->update(['hold_days' => 3]);

        expect($first->fresh()->hold_expires_at->toDateString())->toBe($held->toDateString())
            ->and(moveTo($this, $user, $second, BookingStage::Possible)->hold_expires_at->toDateString())
            ->toBe(today()->addDays(3)->toDateString());
    });
});

describe('clearing a hold', function () {
    it('releases the date when an enquiry is lost', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        // Created below the soft hold and moved up through the route, because
        // the writer is about transitions: a row created directly at Possible
        // never crossed into a holding stage and correctly carries no hold.
        $booking = enquiry(BookingStage::InConversation, today()->addMonths(6)->toDateString());
        currentAccount()->clear();

        $held = moveTo($this, $user, $booking, BookingStage::Possible);
        // The presence half, so the absence below means something.
        expect($held->hold_expires_at)->not->toBeNull();

        $this->actingAs($user)
            ->patchJson('/api/enquiries/'.$booking->id, [
                'stage' => 'lost',
                'lost_reason' => 'went_elsewhere',
            ])
            ->assertOk();

        expect($booking->fresh()->hold_expires_at)->toBeNull();
    });

    it('releases the date when an enquiry goes back to a stage that holds nothing', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $booking = enquiry(BookingStage::New, today()->addMonths(6)->toDateString());
        currentAccount()->clear();

        expect(moveTo($this, $user, $booking, BookingStage::Possible)->hold_expires_at)->not->toBeNull()
            ->and(moveTo($this, $user, $booking, BookingStage::InConversation)->hold_expires_at)->toBeNull();
    });
});

/*
 * The settled rule, asserted rather than assumed: expiry changes what the app
 * says and never what the data is. Nothing runs on a timer, and an artist who
 * lost a date because software decided a hold had lapsed has lost a wedding to
 * a feature.
 */
it('never releases a hold on its own', function () {
    $user = bookingsOwner();
    currentAccount()->set($user->accounts()->first());

    $booking = enquiry(BookingStage::Possible, today()->addMonths(10)->toDateString());
    $event = $booking->events()->sole();

    currentAccount()->clear();

    $held = moveTo($this, $user, $booking, BookingStage::Provisional);

    // A month past the hold, and nobody has touched the record.
    $this->travel(45)->days();

    $stale = $booking->fresh();

    expect($stale->hold_expires_at->toDateString())->toBe($held->hold_expires_at->toDateString())
        // The stage, the date and the event are all exactly as they were.
        ->and($stale->stage)->toBe(BookingStage::Provisional)
        ->and($event->fresh()->event_date->toDateString())->toBe($event->event_date->toDateString());

    // And the only thing that changed is what the app says about it.
    $this->actingAs($user)->getJson('/api/home')->assertOk()
        ->assertJsonPath('data.attention.0.booking_id', $booking->id)
        ->assertJsonPath('data.attention.0.waiting_on', 'artist_not_held');
});
