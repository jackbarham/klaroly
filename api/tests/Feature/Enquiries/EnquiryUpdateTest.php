<?php

use App\Enums\BookingStage;
use App\Enums\LostReason;
use App\Models\Booking;
use App\Models\BookingLine;
use App\Models\Contact;
use App\Models\Event;

/**
 * PATCH /api/enquiries/{booking}: the one way a record crosses the line
 * between the two lists (decision 235).
 *
 * **One route taking a stage, not /convert and /lost.** Named routes are how a
 * state machine is expressed, and the matrix here is deliberately not one: any
 * of the six stages moves to any other and the artist decides. The day
 * somebody adds a precondition to a /convert route because the route's
 * existence invites one, decision 235 has quietly acquired an inference.
 */
function patchStage(Booking $booking, string $stage, array $extra = []): array
{
    return ['stage' => $stage] + $extra;
}

it('moves an enquiry between any two of the four live stages', function (string $from, string $to) {
    $user = bookingsOwner();
    currentAccount()->set($user->accounts()->first());

    $booking = enquiry(BookingStage::from($from), null);

    currentAccount()->clear();

    $this->actingAs($user)
        ->patchJson('/api/enquiries/'.$booking->id, ['stage' => $to])
        ->assertOk()
        ->assertJsonPath('data.stage', $to);

    expect($booking->fresh()->stage)->toBe(BookingStage::from($to));
})->with(function () {
    $live = ['new', 'in_conversation', 'possible', 'quoted'];

    foreach ($live as $from) {
        foreach ($live as $to) {
            if ($from !== $to) {
                yield [$from, $to];
            }
        }
    }
});

describe('converting', function () {
    it('sets converted_at and takes the row out of the list', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $booking = enquiry(BookingStage::Quoted, today()->addDays(216)->toDateString());

        currentAccount()->clear();

        $this->actingAs($user)
            ->patchJson('/api/enquiries/'.$booking->id, ['stage' => 'provisional'])
            ->assertOk()
            // It answers with the record anyway rather than a 204, so the
            // screen can remove the row knowing what happened to it.
            ->assertJsonPath('data.stage', 'provisional');

        expect($booking->fresh()->converted_at)->not->toBeNull();

        $this->actingAs($user)
            ->getJson('/api/enquiries')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    });

    // Converting is reversible for as long as nothing has been signed
    // (business logic 5.3), and a converted_at left behind on a reversed
    // conversion is a lie in the audit trail.
    it('clears converted_at on the way back, and the row returns to the list', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $booking = enquiry(BookingStage::Provisional, null, ['converted_at' => now()->subDays(4)]);

        currentAccount()->clear();

        $this->actingAs($user)
            ->patchJson('/api/enquiries/'.$booking->id, ['stage' => 'possible'])
            ->assertOk()
            ->assertJsonPath('data.stage', 'possible');

        expect($booking->fresh()->converted_at)->toBeNull();

        $this->actingAs($user)
            ->getJson('/api/enquiries')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });

    // A second PATCH to the stage it is already at is not a second conversion.
    it('keeps the original converted_at when the stage does not change', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $converted = now()->subDays(4)->startOfSecond();
        $booking = enquiry(BookingStage::Provisional, null, ['converted_at' => $converted]);

        currentAccount()->clear();

        $this->actingAs($user)
            ->patchJson('/api/enquiries/'.$booking->id, ['stage' => 'provisional'])
            ->assertOk();

        expect($booking->fresh()->converted_at->equalTo($converted))->toBeTrue();
    });
});

describe('marking one as not going ahead', function () {
    it('sets lost_at and the reason, and keeps the row in the list', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $booking = enquiry(BookingStage::Quoted, null);

        currentAccount()->clear();

        $this->actingAs($user)
            ->patchJson('/api/enquiries/'.$booking->id, [
                'stage' => 'lost',
                'lost_reason' => 'already_booked',
            ])
            ->assertOk()
            ->assertJsonPath('data.stage', 'lost')
            ->assertJsonPath('data.lost_reason', 'already_booked')
            // The side comes from the server, because it is a fact about the
            // record and the label beside it is wording.
            ->assertJsonPath('data.lost_side', 'artist');

        $fresh = $booking->fresh();

        expect($fresh->lost_at)->not->toBeNull()
            ->and($fresh->lost_reason)->toBe(LostReason::AlreadyBooked);

        // Lost is archived rather than gone: the screen shows it behind a
        // switch, so the endpoint still returns it.
        $this->actingAs($user)->getJson('/api/enquiries')->assertOk()->assertJsonCount(1, 'data');
    });

    it('refuses to mark one lost without a reason', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $booking = enquiry(BookingStage::Quoted, null);

        currentAccount()->clear();

        $this->actingAs($user)
            ->patchJson('/api/enquiries/'.$booking->id, ['stage' => 'lost'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('lost_reason')
            ->assertJsonPath('errors.lost_reason.0', __('bookings.lost_reason_required'));

        expect($booking->fresh()->stage)->toBe(BookingStage::Quoted);
    });

    it('refuses a reason it does not record', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $booking = enquiry(BookingStage::Quoted, null);

        currentAccount()->clear();

        $this->actingAs($user)
            ->patchJson('/api/enquiries/'.$booking->id, [
                'stage' => 'lost',
                'lost_reason' => 'budget',
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.lost_reason.0', __('bookings.lost_reason_unknown'));
    });

    it('clears both lost_at and the reason on the way back out', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $booking = enquiry(BookingStage::Lost, null, [
            'lost_at' => now()->subWeeks(3),
            'lost_reason' => LostReason::WentElsewhere,
        ]);

        currentAccount()->clear();

        $this->actingAs($user)
            ->patchJson('/api/enquiries/'.$booking->id, ['stage' => 'in_conversation'])
            ->assertOk()
            ->assertJsonPath('data.lost_reason', null)
            ->assertJsonPath('data.lost_side', null);

        $fresh = $booking->fresh();

        expect($fresh->lost_at)->toBeNull()
            ->and($fresh->lost_reason)->toBeNull();
    });

    // Correcting the reason on a record that is already lost is a correction,
    // not a second ending.
    it('keeps the original lost_at when only the reason changes', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $lostAt = now()->subWeeks(3)->startOfSecond();
        $booking = enquiry(BookingStage::Lost, null, [
            'lost_at' => $lostAt,
            'lost_reason' => LostReason::WentElsewhere,
        ]);

        currentAccount()->clear();

        $this->actingAs($user)
            ->patchJson('/api/enquiries/'.$booking->id, [
                'stage' => 'lost',
                'lost_reason' => 'too_expensive',
            ])
            ->assertOk()
            ->assertJsonPath('data.lost_reason', 'too_expensive');

        expect($booking->fresh()->lost_at->equalTo($lostAt))->toBeTrue();
    });

    it('refuses a reason sent with any other stage', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $booking = enquiry(BookingStage::Possible, null);

        currentAccount()->clear();

        $this->actingAs($user)
            ->patchJson('/api/enquiries/'.$booking->id, [
                'stage' => 'quoted',
                'lost_reason' => 'no_reply',
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.lost_reason.0', __('bookings.lost_reason_not_allowed'));
    });

    /**
     * The other half of that rule, and the reason it is prohibited_unless
     * rather than missing_unless: a client that always sends both fields and
     * puts null in the second when there is no reason is saying something
     * true, and refusing it would buy nothing.
     */
    it('accepts an explicit null reason with another stage', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $booking = enquiry(BookingStage::Possible, null);

        currentAccount()->clear();

        $this->actingAs($user)
            ->patchJson('/api/enquiries/'.$booking->id, [
                'stage' => 'quoted',
                'lost_reason' => null,
            ])
            ->assertOk()
            ->assertJsonPath('data.stage', 'quoted');
    });
});

describe('what this route refuses to touch', function () {
    /**
     * **A 422 and not a 403.** The caller is allowed to be here; the record is
     * the wrong kind. Changing the stage of a signed job through a route built
     * for a list of maybes is a downgrade of something signed.
     */
    it('refuses a booking at confirmed or beyond', function (string $stage) {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $booking = enquiry(BookingStage::from($stage), null);

        currentAccount()->clear();

        $this->actingAs($user)
            ->patchJson('/api/enquiries/'.$booking->id, ['stage' => 'possible'])
            ->assertStatus(422)
            ->assertJsonPath('errors.stage.0', __('bookings.not_an_enquiry'));

        expect($booking->fresh()->stage)->toBe(BookingStage::from($stage));
    })->with(['confirmed', 'completed', 'closed', 'cancelled']);

    // Paired with the refusals above, so "422 for everything" cannot pass:
    // provisional is accepted as a source, because converting is reversible.
    it('accepts a provisional booking as a source', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $booking = enquiry(BookingStage::Provisional, null);

        currentAccount()->clear();

        $this->actingAs($user)
            ->patchJson('/api/enquiries/'.$booking->id, ['stage' => 'quoted'])
            ->assertOk()
            ->assertJsonPath('data.stage', 'quoted');
    });

    it('refuses a stage no enquiry can be moved to', function (string $stage) {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $booking = enquiry(BookingStage::Possible, null);

        currentAccount()->clear();

        $this->actingAs($user)
            ->patchJson('/api/enquiries/'.$booking->id, ['stage' => $stage])
            ->assertStatus(422)
            ->assertJsonPath('errors.stage.0', __('bookings.stage_not_settable'));
    })->with(['confirmed', 'completed', 'closed', 'cancelled', 'nonsense']);

    it('refuses a request with no stage at all', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $booking = enquiry(BookingStage::Possible, null);

        currentAccount()->clear();

        $this->actingAs($user)
            ->patchJson('/api/enquiries/'.$booking->id, [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('stage');
    });

    /**
     * **It is not a general booking update.** Everything else on the record is
     * asserted unchanged rather than left to the reader, because "the
     * controller only names four columns" is true right up until somebody adds
     * a fifth.
     */
    it('moves nothing on the booking but the stage and its two timestamps', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $contact = Contact::factory()->create();
        $booking = Booking::factory()->possible()->create([
            'contact_id' => $contact->id,
            'currency' => 'EUR',
            'enquiry_message' => 'The original message.',
        ]);
        BookingLine::factory()->create(['booking_id' => $booking->id, 'quantity' => 2, 'unit_price_minor' => 6500]);
        $event = Event::factory()->create([
            'booking_id' => $booking->id,
            'event_date' => today()->addDays(200)->toDateString(),
        ]);

        // Made while the account is still bound, because a scoped model cannot
        // be created without one. It exists only to be ignored.
        $decoy = Contact::factory()->create();

        currentAccount()->clear();

        $this->actingAs($user)
            ->patchJson('/api/enquiries/'.$booking->id, [
                'stage' => 'quoted',
                // Everything below is named by no rule, so none of it is read.
                'contact_id' => $decoy->id,
                'currency' => 'GBP',
                'enquiry_message' => 'Rewritten.',
                'hold_expires_at' => today()->addDays(10)->toDateString(),
                'confirmed_at' => now()->toDateTimeString(),
            ])
            ->assertOk();

        $fresh = $booking->fresh();

        expect($fresh->stage)->toBe(BookingStage::Quoted)
            ->and($fresh->contact_id)->toBe($contact->id)
            ->and($fresh->currency)->toBe('EUR')
            ->and($fresh->enquiry_message)->toBe('The original message.')
            ->and($fresh->hold_expires_at)->toBeNull()
            ->and($fresh->confirmed_at)->toBeNull()
            ->and($fresh->lines)->toHaveCount(1)
            ->and($event->fresh()->event_date->format('Y-m-d'))->toBe(today()->addDays(200)->toDateString());
    });
});

describe('last touched', function () {
    /**
     * **Against a clock, not against "not what it was".**
     *
     * A write that set the column to the epoch, or to the row's created_at, or
     * to a year hence would all satisfy "it changed", and every one of those is
     * a broken enquiries list. The instant is named, so only the right answer
     * passes.
     */
    it('moves last_touched_at to now on every write', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $booking = enquiry(BookingStage::Possible, null, ['last_touched_at' => now()->subDays(30)]);

        currentAccount()->clear();

        $moment = now()->addHours(3)->startOfSecond();

        $this->travelTo($moment, function () use ($user, $booking) {
            $this->actingAs($user)
                ->patchJson('/api/enquiries/'.$booking->id, ['stage' => 'quoted'])
                ->assertOk();
        });

        expect($booking->fresh()->last_touched_at->equalTo($moment))->toBeTrue();
    });

    /**
     * The one that proves the write and the ordering agree.
     *
     * The list is ordered by neglect, so touching the most neglected row has to
     * move it off the top. Asserting the column alone would pass against an
     * endpoint that ordered by id, and asserting the order alone would pass
     * against a write that touched nothing if the ids happened to fall right.
     */
    it('moves the row\'s place in the list with it', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $stalest = enquiry(BookingStage::Possible, null, ['last_touched_at' => now()->subDays(40)]);
        $middle = enquiry(BookingStage::Quoted, null, ['last_touched_at' => now()->subDays(20)]);
        $freshest = enquiry(BookingStage::Possible, null, ['last_touched_at' => now()->subDays(2)]);

        currentAccount()->clear();

        $before = $this->actingAs($user)->getJson('/api/enquiries')->assertOk()->json('data.*.id');

        expect($before)->toBe([$stalest->id, $middle->id, $freshest->id]);

        $this->actingAs($user)
            ->patchJson('/api/enquiries/'.$stalest->id, ['stage' => 'quoted'])
            ->assertOk();

        $after = $this->actingAs($user)->getJson('/api/enquiries')->assertOk()->json('data.*.id');

        // Touched now, so it is the least neglected of the three and goes last.
        expect($after)->toBe([$middle->id, $freshest->id, $stalest->id]);
    });
});

/**
 * The write answers with the detail read's shape, asserted against the same
 * two constants that test asserts against rather than a third list typed here.
 * The screen replaces what it is holding rather than following the write with
 * a read, so any drift between the two would be a field the screen loses on
 * every save.
 */
it('answers with the same shape the detail read returns', function () {
    $user = bookingsOwner();
    currentAccount()->set($user->accounts()->first());

    $booking = enquiry(BookingStage::Possible, today()->addDays(216)->toDateString(), [
        'enquiry_message' => 'Five of us next June.',
    ]);

    currentAccount()->clear();

    $written = $this->actingAs($user)
        ->patchJson('/api/enquiries/'.$booking->id, ['stage' => 'quoted'])
        ->assertOk()
        ->json('data');

    $read = $this->actingAs($user)->getJson('/api/enquiries/'.$booking->id)->assertOk()->json('data');

    expect(array_keys($written))->toBe([...ENQUIRY_KEYS, ...ENQUIRY_DETAIL_KEYS])
        ->and($written)->toBe($read);
});

/**
 * Not found, not forbidden, and the second half is the half that means
 * anything: an assertion that another account's booking is a 404 passes just
 * as happily when every booking is a 404, which is what happened before
 * bootstrap/app.php put BindCurrentAccount ahead of SubstituteBindings.
 */
it('does not find another account\'s booking, and does write its own', function () {
    $mine = bookingsOwner();
    $theirs = bookingsOwner();

    currentAccount()->set($theirs->accounts()->first());
    $intruder = enquiry(BookingStage::Possible, null);

    currentAccount()->set($mine->accounts()->first());
    $ours = enquiry(BookingStage::Possible, null);

    currentAccount()->clear();

    $this->actingAs($mine)
        ->patchJson('/api/enquiries/'.$intruder->id, ['stage' => 'quoted'])
        ->assertNotFound();

    $this->actingAs($mine)
        ->patchJson('/api/enquiries/'.$ours->id, ['stage' => 'quoted'])
        ->assertOk();

    expect($intruder->fresh()->stage)->toBe(BookingStage::Possible)
        ->and($ours->fresh()->stage)->toBe(BookingStage::Quoted);
});
