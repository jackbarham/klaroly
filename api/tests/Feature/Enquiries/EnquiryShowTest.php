<?php

use App\Enums\BookingStage;
use App\Enums\LostReason;
use App\Models\Booking;
use App\Models\Contact;
use App\Models\Event;
use App\Models\Note;
use App\Models\PartyMember;

/**
 * GET /api/enquiries/{booking}: one enquiry as its own screen.
 *
 * The keys are asserted as ENQUIRY_KEYS followed by ENQUIRY_DETAIL_KEYS, both
 * from tests/Pest.php, rather than as a list of seventeen typed out here. The
 * detail resource composes the list resource, so an assertion written out in
 * full would go on passing when only one of the two moved, which is the drift
 * it exists to catch.
 */

/**
 * A note against a booking, written at a given moment.
 */
function noteOn(Booking $booking, string $body, ?string $writtenAt = null): Note
{
    return Note::factory()->create([
        'booking_id' => $booking->id,
        'contact_id' => null,
        'body' => $body,
        'created_at' => $writtenAt ?? now(),
    ]);
}

it('returns every key the list row has, plus the three a detail adds', function () {
    $user = bookingsOwner();
    currentAccount()->set($user->accounts()->first());

    $booking = enquiry(BookingStage::Quoted, today()->addDays(216)->toDateString(), [
        'enquiry_message' => 'We are at Ashen Hollow next June and there would be five of us.',
    ]);

    PartyMember::factory()->count(5)->create(['booking_id' => $booking->id]);
    noteOn($booking, 'Quote went out on Monday.');

    currentAccount()->clear();

    $response = $this->actingAs($user)->getJson('/api/enquiries/'.$booking->id)->assertOk();

    expect(array_keys($response->json('data')))->toBe([...ENQUIRY_KEYS, ...ENQUIRY_DETAIL_KEYS])
        ->and(array_keys($response->json('data.notes.0')))->toBe(ENQUIRY_NOTE_KEYS);

    $response
        ->assertJsonPath('data.id', $booking->id)
        ->assertJsonPath('data.stage', 'quoted')
        ->assertJsonPath('data.enquiry_message', 'We are at Ashen Hollow next June and there would be five of us.')
        ->assertJsonPath('data.party_size', 5)
        ->assertJsonPath('data.notes.0.body', 'Quote went out on Monday.');
});

it('sends the notes newest first', function () {
    $user = bookingsOwner();
    currentAccount()->set($user->accounts()->first());

    $booking = enquiry(BookingStage::Possible, null);

    noteOn($booking, 'First call.', now()->subDays(20)->toDateTimeString());
    noteOn($booking, 'Second call.', now()->subDays(4)->toDateTimeString());

    currentAccount()->clear();

    $bodies = $this->actingAs($user)
        ->getJson('/api/enquiries/'.$booking->id)
        ->assertOk()
        ->json('data.notes.*.body');

    expect($bodies)->toBe(['Second call.', 'First call.']);
});

/**
 * Schema 5.17 makes both booking_id and contact_id nullable with a check that
 * one of them is set, so a note can belong to the person rather than to the
 * job. That one is not a note about this enquiry and belongs on the contact's
 * card.
 *
 * Paired with an assertion that the booking's own note IS here, because an
 * absence on its own passes just as happily against an endpoint that sends no
 * notes at all.
 */
it('sends the booking\'s notes and not the contact\'s', function () {
    $user = bookingsOwner();
    currentAccount()->set($user->accounts()->first());

    $contact = Contact::factory()->create();
    $booking = Booking::factory()->possible()->create(['contact_id' => $contact->id]);

    noteOn($booking, 'About this enquiry.');
    Note::factory()->create([
        'booking_id' => null,
        'contact_id' => $contact->id,
        'body' => 'About the person.',
    ]);

    currentAccount()->clear();

    $bodies = $this->actingAs($user)
        ->getJson('/api/enquiries/'.$booking->id)
        ->assertOk()
        ->json('data.notes.*.body');

    expect($bodies)->toBe(['About this enquiry.']);
});

/**
 * The one that stops two answers to one question.
 *
 * EnquiryDetailResource composes EnquiryResource rather than repeating it, so
 * the detail cannot decide which event this booking means, what it is waiting
 * on or what it clashes with for itself. This asserts that against a record
 * where all three have something to say: a cold enquiry, on a Saturday with a
 * confirmed booking and two other enquiries on it, whose own booking has a
 * trial as well as a main day.
 */
it('gives the same event, waiting on and clash as the list does', function () {
    $user = bookingsOwner();
    currentAccount()->set($user->accounts()->first());

    $date = today()->addDays(279)->toDateString();

    eventOn($date, ['stage' => BookingStage::Confirmed]);
    enquiry(BookingStage::Possible, $date);
    enquiry(BookingStage::Quoted, $date);

    $booking = coldEnquiry(BookingStage::Quoted, $date);
    Event::factory()->create([
        'booking_id' => $booking->id,
        'type' => 'trial',
        'event_date' => today()->addDays(200)->toDateString(),
    ]);

    currentAccount()->clear();

    $row = collect($this->actingAs($user)->getJson('/api/enquiries')->assertOk()->json('data'))
        ->firstWhere('id', $booking->id);

    $detail = $this->actingAs($user)->getJson('/api/enquiries/'.$booking->id)->assertOk()->json('data');

    // Every key the list sends, compared as a whole rather than field by
    // field, so a fourteenth answer added later is covered without anybody
    // remembering to add it here.
    expect(array_intersect_key($detail, array_flip(ENQUIRY_KEYS)))->toBe($row)
        // And named individually, so a failure says which of the three drifted.
        ->and($detail['event'])->toBe($row['event'])
        ->and($detail['waiting_on'])->toBe('artist_enquiry_cold')
        ->and($detail['clash'])->toBe(['confirmed' => 1, 'provisional' => 0, 'others' => 2]);
});

// The three fields are present and empty rather than absent, so the screen
// reads them without testing whether the key exists.
it('sends null, null and an empty array for an enquiry with none of the three', function () {
    $user = bookingsOwner();
    currentAccount()->set($user->accounts()->first());

    $booking = enquiry(BookingStage::New, null, ['enquiry_message' => null]);

    currentAccount()->clear();

    $data = $this->actingAs($user)->getJson('/api/enquiries/'.$booking->id)->assertOk()->json('data');

    expect(array_keys($data))->toBe([...ENQUIRY_KEYS, ...ENQUIRY_DETAIL_KEYS])
        ->and($data['enquiry_message'])->toBeNull()
        ->and($data['party_size'])->toBeNull()
        ->and($data['notes'])->toBe([]);
});

/**
 * Null at zero, never zero, and the distinction is the field's whole value: a
 * party of nobody is not a thing anybody books, so a zero could only ever mean
 * "the party sheet is empty", which is "not known yet" wearing a number.
 *
 * Paired with a count, so "null" cannot pass by being null for everything.
 */
it('counts the party, and says null rather than nought when nobody has said', function () {
    $user = bookingsOwner();
    currentAccount()->set($user->accounts()->first());

    $counted = enquiry(BookingStage::Possible, null);
    PartyMember::factory()->count(3)->create(['booking_id' => $counted->id]);

    $unsaid = enquiry(BookingStage::Possible, null);

    currentAccount()->clear();

    $this->actingAs($user)
        ->getJson('/api/enquiries/'.$counted->id)
        ->assertOk()
        ->assertJsonPath('data.party_size', 3);

    $this->actingAs($user)
        ->getJson('/api/enquiries/'.$unsaid->id)
        ->assertOk()
        ->assertJsonPath('data.party_size', null);
});

describe('what this route will find', function () {
    // The five the list shows, which is what this route is: that row opened.
    it('finds an enquiry at any of the five stages the list holds', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $bookings = [];

        foreach (Booking::LISTED_STAGES as $stage) {
            $bookings[$stage->value] = enquiry($stage, null, $stage === BookingStage::Lost
                ? ['lost_reason' => LostReason::NoReply]
                : []);
        }

        currentAccount()->clear();

        foreach ($bookings as $value => $booking) {
            $this->actingAs($user)
                ->getJson('/api/enquiries/'.$booking->id)
                ->assertOk()
                ->assertJsonPath('data.stage', $value);
        }
    });

    // Provisional is the asymmetry, and it is deliberate. The write accepts a
    // provisional record, because converting is reversible, but the list no
    // longer shows one, so neither does this. A screen that has just converted
    // holds the object it was answered with; a refresh belongs to the bookings
    // list.
    it('does not find a provisional booking', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $booking = enquiry(BookingStage::Provisional, null);

        currentAccount()->clear();

        $this->actingAs($user)->getJson('/api/enquiries/'.$booking->id)->assertNotFound();
    });

    it('does not find a confirmed booking', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $booking = enquiry(BookingStage::Confirmed, null);

        currentAccount()->clear();

        $this->actingAs($user)->getJson('/api/enquiries/'.$booking->id)->assertNotFound();
    });
});

/**
 * **Not found, not forbidden**, and the second half of this test is the half
 * that means anything.
 *
 * A tenancy assertion that another account's row is a 404 passes just as
 * happily when every row is a 404, which is exactly what happened here before
 * bootstrap/app.php put BindCurrentAccount ahead of SubstituteBindings: the
 * binding query ran with no tenant, the global scope became `where 1 = 0`, and
 * the caller's own booking was a 404 too. So the caller's own row is asserted
 * found in the same test, or this cannot tell the fix from the bug.
 */
it('does not find another account\'s enquiry, and does find its own', function () {
    $mine = bookingsOwner();
    $theirs = bookingsOwner();

    currentAccount()->set($theirs->accounts()->first());
    $intruder = enquiry(BookingStage::Possible, null);

    currentAccount()->set($mine->accounts()->first());
    $ours = enquiry(BookingStage::Possible, null);

    currentAccount()->clear();

    $this->actingAs($mine)->getJson('/api/enquiries/'.$intruder->id)->assertNotFound();

    $this->actingAs($mine)
        ->getJson('/api/enquiries/'.$ours->id)
        ->assertOk()
        ->assertJsonPath('data.id', $ours->id);
});
