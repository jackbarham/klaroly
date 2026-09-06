<?php

use App\Enums\BookingStage;
use App\Enums\LostReason;
use App\Models\Booking;
use App\Models\BookingLine;
use App\Models\Contact;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

it('returns every enquiry with every key the app expects', function () {
    $user = bookingsOwner();
    currentAccount()->set($user->accounts()->first());

    $contact = Contact::factory()->create(['first_name' => 'Saoirse', 'last_name' => 'Gallagher']);
    $captured = Booking::factory()->confirmed()->create();
    Event::factory()->create([
        'booking_id' => $captured->id,
        'event_date' => today()->subDays(50)->toDateString(),
    ]);

    $booking = Booking::factory()->quoted()->create([
        'contact_id' => $contact->id,
        'source' => 'captured_at_event',
        'source_booking_id' => $captured->id,
    ]);
    BookingLine::factory()->create(['booking_id' => $booking->id, 'quantity' => 2, 'unit_price_minor' => 43000]);
    Event::factory()->create([
        'booking_id' => $booking->id,
        'event_date' => today()->addDays(216)->toDateString(),
        'venue_name' => 'The Yard at Hollingfen',
        'city' => 'Hollingfen',
        'location_type' => 'venue',
    ]);

    currentAccount()->clear();

    $response = $this->actingAs($user)->getJson('/api/enquiries')->assertOk();

    expect(array_keys($response->json('data.0')))->toBe(ENQUIRY_KEYS)
        ->and(array_keys($response->json('data.0.event')))->toBe(ENQUIRY_EVENT_KEYS)
        ->and(array_keys($response->json('data.0.source_booking')))->toBe(SOURCE_BOOKING_KEYS);

    $response
        ->assertJsonPath('data.0.id', $booking->id)
        ->assertJsonPath('data.0.stage', 'quoted')
        ->assertJsonPath('data.0.client_name', 'Saoirse Gallagher')
        ->assertJsonPath('data.0.contact_id', $contact->id)
        ->assertJsonPath('data.0.source', 'captured_at_event')
        ->assertJsonPath('data.0.total_minor', 86000)
        ->assertJsonPath('data.0.currency', 'GBP')
        ->assertJsonPath('data.0.event.type', 'main')
        ->assertJsonPath('data.0.event.venue_name', 'The Yard at Hollingfen')
        ->assertJsonPath('data.0.event.location_type', 'venue')
        ->assertJsonPath('data.0.lost_reason', null)
        ->assertJsonPath('data.0.lost_side', null)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('meta.returned', 1)
        ->assertJsonPath('meta.truncated', false);
});

// The id is the BOOKING's, because that is what a row links to. Saying so with
// an assertion rather than only in a docblock, because "id" is exactly the
// field somebody later swaps for an event's without noticing.
it('sends the booking id, and one row for a booking with two events', function () {
    $user = bookingsOwner();
    currentAccount()->set($user->accounts()->first());

    $booking = enquiry(BookingStage::Possible, today()->addDays(265)->toDateString(), [], ['type' => 'main']);
    Event::factory()->create([
        'booking_id' => $booking->id,
        'type' => 'trial',
        'event_date' => today()->addDays(189)->toDateString(),
    ]);

    currentAccount()->clear();

    $this->actingAs($user)
        ->getJson('/api/enquiries')
        ->assertOk()
        // One conversation, one row, showing the wedding rather than the trial.
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $booking->id)
        ->assertJsonPath('data.0.event.type', 'main')
        ->assertJsonPath('data.0.event.date', today()->addDays(265)->toDateString());
});

/**
 * Whether there is a trial as well as the day the row is shown by.
 *
 * The row says "29 May 2027 · Marlbrook Hall · and a trial", and it cannot say
 * it from `event` alone: that field carries the main day, so a booking with a
 * trial in March and the wedding in May looks exactly like one with no trial
 * at all. This is the field that tells them apart.
 */
describe('a trial as well as the main day', function () {
    it('says so on a booking that has both', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $booking = enquiry(BookingStage::Possible, today()->addDays(265)->toDateString(), [], ['type' => 'main']);
        Event::factory()->create([
            'booking_id' => $booking->id,
            'type' => 'trial',
            'event_date' => today()->addDays(189)->toDateString(),
        ]);

        currentAccount()->clear();

        $this->actingAs($user)
            ->getJson('/api/enquiries')
            ->assertOk()
            // One row, showing the wedding, and saying there is a trial too.
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.event.type', 'main')
            ->assertJsonPath('data.0.has_trial', true);
    });

    // Paired, so "true" cannot pass by being true for everything.
    it('says no on a booking with only a main day', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        enquiry(BookingStage::Possible, today()->addDays(265)->toDateString(), [], ['type' => 'main']);

        currentAccount()->clear();

        $this->actingAs($user)
            ->getJson('/api/enquiries')
            ->assertOk()
            ->assertJsonPath('data.0.has_trial', false);
    });

    /**
     * True on a booking whose only event is the trial, which looks redundant
     * beside an `event` of type trial and is not: the row draws its date from
     * `event` and this from here, so a rule reading "there is a trial and it is
     * not the one being shown" would make the two fields answer one question
     * between them.
     */
    it('says yes on a standalone trial, whose shown event is that trial', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        enquiry(BookingStage::Possible, today()->addDays(190)->toDateString(), [], ['type' => 'trial']);

        currentAccount()->clear();

        $this->actingAs($user)
            ->getJson('/api/enquiries')
            ->assertOk()
            ->assertJsonPath('data.0.event.type', 'trial')
            ->assertJsonPath('data.0.has_trial', true);
    });

    it('says no on an enquiry with no dates at all', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        enquiry(BookingStage::InConversation, null);

        currentAccount()->clear();

        $this->actingAs($user)
            ->getJson('/api/enquiries')
            ->assertOk()
            ->assertJsonPath('data.0.event', null)
            ->assertJsonPath('data.0.has_trial', false);
    });
});

// A standalone trial or a shoot is an enquiry with no main day. Showing it with
// no date at all would be worse than showing the date it does have, which is
// the fallback ContactActivity::mainEvent() already makes for the contacts
// screen and which this endpoint reuses rather than restating.
it('falls back to the earliest event when an enquiry has no main day', function () {
    $user = bookingsOwner();
    currentAccount()->set($user->accounts()->first());

    enquiry(BookingStage::Possible, today()->addDays(190)->toDateString(), [], ['type' => 'trial']);

    currentAccount()->clear();

    $this->actingAs($user)
        ->getJson('/api/enquiries')
        ->assertOk()
        ->assertJsonPath('data.0.event.type', 'trial')
        ->assertJsonPath('data.0.event.date', today()->addDays(190)->toDateString());
});

// "Next summer, we have not booked the venue yet". Normal, often winnable, and
// the whole reason this endpoint is not a filter over GET /api/events: there
// would be no row.
it('serialises an enquiry with no date, and orders it without error', function () {
    $user = bookingsOwner();
    currentAccount()->set($user->accounts()->first());

    enquiry(BookingStage::InConversation, null, ['last_touched_at' => now()->subDays(8)]);
    enquiry(BookingStage::Possible, today()->addDays(30)->toDateString(), ['last_touched_at' => now()->subDays(2)]);

    currentAccount()->clear();

    $this->actingAs($user)
        ->getJson('/api/enquiries')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        // Eight days beats two, so the dateless one is at the top rather than
        // sorted out of the list by a null it has no date to fill.
        ->assertJsonPath('data.0.event', null)
        ->assertJsonPath('data.0.clash', null)
        ->assertJsonPath('data.1.event.date', today()->addDays(30)->toDateString());
});

it('sends the date as a local calendar date, never an instant', function () {
    $user = bookingsOwner();
    currentAccount()->set($user->accounts()->first());

    // Late in the evening is where a timezone conversion would show: sent as an
    // instant in UTC this would arrive as the following day for the eight
    // months the clocks are forward.
    enquiry(BookingStage::Possible, '2027-06-14', [], ['start_time' => '23:30:00']);

    currentAccount()->clear();

    $this->actingAs($user)
        ->getJson('/api/enquiries')
        ->assertOk()
        ->assertJsonPath('data.0.event.date', '2027-06-14');
});

describe('the source booking', function () {
    it('carries enough of the booking it was captured at to name it', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $host = Contact::factory()->create(['first_name' => 'Elspeth', 'last_name' => 'Rowntree']);
        $wedding = Booking::factory()->confirmed()->create(['contact_id' => $host->id]);
        Event::factory()->create([
            'booking_id' => $wedding->id,
            'event_date' => today()->subDays(30)->toDateString(),
        ]);

        enquiry(BookingStage::New, null, [
            'source' => 'captured_at_event',
            'source_booking_id' => $wedding->id,
        ]);

        currentAccount()->clear();

        $this->actingAs($user)
            ->getJson('/api/enquiries')
            ->assertOk()
            ->assertJsonPath('data.0.source_booking.id', $wedding->id)
            ->assertJsonPath('data.0.source_booking.client_name', 'Elspeth Rowntree')
            ->assertJsonPath('data.0.source_booking.date', today()->subDays(30)->toDateString());
    });

    it('is null on an enquiry that arrived on its own', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        enquiry(BookingStage::New, null, ['source' => 'web_form']);

        currentAccount()->clear();

        $this->actingAs($user)
            ->getJson('/api/enquiries')
            ->assertOk()
            ->assertJsonPath('data.0.source_booking', null);
    });
});

describe('which stages are enquiries', function () {
    /**
     * **The boundary is provisional, not confirmed** (decision 235). Converting
     * is the artist's own tap and nothing in the system promotes an enquiry on
     * its own; signing and depositing turn provisional into confirmed, and what
     * that changes is the calendar mark rather than which list the record is in.
     *
     * The two halves are one test on purpose. An absence assertion on its own
     * passes just as happily against an endpoint that returns nothing, so the
     * five that must be absent are asserted beside the five that must be
     * present, in one payload where both are true.
     */
    it('returns the four live stages and lost, and nothing from provisional onwards', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $wanted = [
            BookingStage::New,
            BookingStage::InConversation,
            BookingStage::Possible,
            BookingStage::Quoted,
            BookingStage::Lost,
        ];

        $unwanted = [
            BookingStage::Provisional,
            BookingStage::Confirmed,
            BookingStage::Completed,
            BookingStage::Closed,
            BookingStage::Cancelled,
        ];

        foreach ([...$wanted, ...$unwanted] as $stage) {
            enquiry($stage, today()->addDays(100)->toDateString());
        }

        currentAccount()->clear();

        $stages = $this->actingAs($user)->getJson('/api/enquiries')->assertOk()->json('data.*.stage');

        expect($stages)->toHaveCount(5)
            ->and(array_values(array_intersect($stages, array_map(fn ($s) => $s->value, $wanted))))
            ->toHaveCount(5);

        foreach ($unwanted as $stage) {
            expect($stages)->not->toContain($stage->value);
        }
    });

    it('counts only the enquiry stages in the total', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        enquiry(BookingStage::Possible, today()->addDays(20)->toDateString());
        enquiry(BookingStage::Confirmed, today()->addDays(21)->toDateString());
        enquiry(BookingStage::Cancelled, today()->addDays(22)->toDateString());

        currentAccount()->clear();

        $this->actingAs($user)
            ->getJson('/api/enquiries')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.returned', 1);
    });
});

describe('tenancy', function () {
    it('never shows another account\'s enquiries', function () {
        $mine = bookingsOwner();
        $theirs = bookingsOwner();

        currentAccount()->set($theirs->accounts()->first());
        enquiry(BookingStage::Possible, today()->addDays(5)->toDateString(), [], ['venue_name' => 'Theirs']);

        currentAccount()->set($mine->accounts()->first());
        enquiry(BookingStage::Possible, today()->addDays(6)->toDateString(), [], ['venue_name' => 'Mine']);

        currentAccount()->clear();

        $this->actingAs($mine)
            ->getJson('/api/enquiries')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.event.venue_name', 'Mine')
            ->assertJsonPath('meta.total', 1);
    });

    /**
     * The one that matters, and the one a where('account_id', ...) written by
     * hand for the clash counts would pass while getting wrong.
     *
     * The list is one scope and the clash query is another. An endpoint that
     * scoped the enquiries and then counted the date through an unscoped join
     * would return my enquiry carrying somebody else's Saturday, and the
     * artist would be told to ring round a date that is hers alone.
     */
    it('never counts another account\'s bookings in a clash on an enquiry that is mine', function () {
        $mine = bookingsOwner();
        $theirs = bookingsOwner();

        $date = today()->addDays(280)->toDateString();

        currentAccount()->set($theirs->accounts()->first());
        eventOn($date);
        enquiry(BookingStage::Possible, $date);

        currentAccount()->set($mine->accounts()->first());
        enquiry(BookingStage::Quoted, $date);

        currentAccount()->clear();

        $this->actingAs($mine)
            ->getJson('/api/enquiries')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            // Their confirmed booking and their possible enquiry are both on
            // the same day. Mine is alone on it.
            ->assertJsonPath('data.0.clash', null);
    });
});

describe('the order', function () {
    /**
     * **Staleness is the default order, and New is pinned above it** (decision
     * 236). The top of the list is the thing nobody has touched for longest,
     * except that an enquiry at New has the freshest timestamp in the list and
     * would sort to the bottom, which is exactly backwards, because it is the
     * one nobody has looked at.
     */
    it('puts new first newest-first, then the rest oldest-touched first, then lost', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $staleNew = enquiry(BookingStage::New, null, ['last_touched_at' => now()->subDays(5)]);
        $freshNew = enquiry(BookingStage::New, null, ['last_touched_at' => now()->subHours(7)]);
        $freshQuoted = enquiry(BookingStage::Quoted, null, ['last_touched_at' => now()->subDay()]);
        $staleQuoted = enquiry(BookingStage::Quoted, null, ['last_touched_at' => now()->subDays(23)]);
        $stalePossible = enquiry(BookingStage::Possible, null, ['last_touched_at' => now()->subDays(34)]);
        // Touched longest ago of the lot, and still last, because the screen
        // groups the archive separately.
        $lost = enquiry(BookingStage::Lost, null, ['last_touched_at' => now()->subDays(62)]);

        currentAccount()->clear();

        $ids = $this->actingAs($user)->getJson('/api/enquiries')->assertOk()->json('data.*.id');

        expect($ids)->toBe([
            $freshNew->id,
            $staleNew->id,
            $stalePossible->id,
            $staleQuoted->id,
            $freshQuoted->id,
            $lost->id,
        ]);
    });

    it('returns the same order twice running', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        // Four enquiries touched at the same instant, with nothing to separate
        // them but their ids, which is the case an incomplete ordering gets
        // wrong.
        $touched = now()->subDays(9);

        foreach ([BookingStage::Possible, BookingStage::Quoted, BookingStage::InConversation, BookingStage::Possible] as $stage) {
            enquiry($stage, null, ['last_touched_at' => $touched]);
        }

        currentAccount()->clear();

        $first = $this->actingAs($user)->getJson('/api/enquiries')->json('data.*.id');
        $again = $this->actingAs($user)->getJson('/api/enquiries')->json('data.*.id');

        expect($first)->toBe($again)->toHaveCount(4);
    });
});

/**
 * **A total of nought and no price are different facts**, and the screen has
 * to say so: "No price yet" against an enquiry nobody has quoted, which is
 * most of them, and "£0" against a job somebody is doing for nothing.
 *
 * Neither the total nor the stage can tell them apart on its own, which is why
 * App\Services\BookingPricing::isPriced() is the predicate and why it lives
 * beside the sum it qualifies rather than in a resource.
 */
describe('the total', function () {
    it('is null when nobody has priced the enquiry', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        enquiry(BookingStage::Possible, null);

        currentAccount()->clear();

        $this->actingAs($user)
            ->getJson('/api/enquiries')
            ->assertOk()
            ->assertJsonPath('data.0.total_minor', null)
            // The currency goes either way, because it is a fact about the
            // booking rather than about the price: a job in euros nobody has
            // quoted is still a job in euros.
            ->assertJsonPath('data.0.currency', 'GBP');
    });

    // The pair that makes the distinction mean something. A line at nought is
    // a price somebody chose.
    it('is nought, not null, for an itemised job priced at nothing', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $booking = enquiry(BookingStage::Quoted, null);
        BookingLine::factory()->create([
            'booking_id' => $booking->id,
            'quantity' => 1,
            'unit_price_minor' => 0,
        ]);

        currentAccount()->clear();

        $this->actingAs($user)
            ->getJson('/api/enquiries')
            ->assertOk()
            ->assertJsonPath('data.0.total_minor', 0);
    });

    it('is nought for a fixed price of nothing, and null when fixed mode has no price yet', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $free = enquiry(BookingStage::Quoted, null, [
            'pricing_mode' => 'fixed',
            'fixed_price_minor' => 0,
            'last_touched_at' => now()->subDays(30),
        ]);
        $unset = enquiry(BookingStage::Quoted, null, [
            'pricing_mode' => 'fixed',
            'fixed_price_minor' => null,
            'last_touched_at' => now()->subDays(10),
        ]);

        currentAccount()->clear();

        $rows = collect($this->actingAs($user)->getJson('/api/enquiries')->assertOk()->json('data'))
            ->keyBy('id');

        expect($rows[$free->id]['total_minor'])->toBe(0)
            ->and($rows[$unset->id]['total_minor'])->toBeNull();
    });

    it('sends the priced total when there is one', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $booking = enquiry(BookingStage::Quoted, null);
        BookingLine::factory()->create([
            'booking_id' => $booking->id,
            'quantity' => 2,
            'unit_price_minor' => 43000,
        ]);

        currentAccount()->clear();

        $this->actingAs($user)
            ->getJson('/api/enquiries')
            ->assertOk()
            ->assertJsonPath('data.0.total_minor', 86000);
    });
});

describe('waiting on', function () {
    /**
     * The case that fails before App\Services\WaitingOnResolver is widened, and
     * the reason the widening is in this prompt rather than the screen's.
     *
     * enquiryCold() fired only at Possible, which was right when the only
     * consumer was the home screen and is wrong for this list: a quote sent
     * three weeks ago with no reply is the most actionable row on the screen.
     * With cold resolved on the server the "Gone quiet" group is simply every
     * row whose waiting_on is artist_enquiry_cold, and the threshold never has
     * to reach the client.
     */
    it('calls a quoted enquiry cold once nobody has touched it past the threshold', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        coldEnquiry(BookingStage::Quoted, today()->addDays(216)->toDateString());

        currentAccount()->clear();

        $this->actingAs($user)
            ->getJson('/api/enquiries')
            ->assertOk()
            ->assertJsonPath('data.0.waiting_on', 'artist_enquiry_cold');
    });

    it('calls an in-conversation enquiry cold on the same terms', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        coldEnquiry(BookingStage::InConversation, null);

        currentAccount()->clear();

        $this->actingAs($user)
            ->getJson('/api/enquiries')
            ->assertOk()
            ->assertJsonPath('data.0.waiting_on', 'artist_enquiry_cold');
    });

    // Paired with the two above, so "cold" cannot pass by being the answer to
    // everything: a quoted enquiry touched this morning is waiting on nobody.
    it('says nothing about a quoted enquiry touched today', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        enquiry(BookingStage::Quoted, today()->addDays(216)->toDateString(), ['last_touched_at' => now()]);

        currentAccount()->clear();

        $this->actingAs($user)
            ->getJson('/api/enquiries')
            ->assertOk()
            ->assertJsonPath('data.0.waiting_on', null);
    });
});

/**
 * Business logic 5.2, and decision 2026-09-06.1804.
 *
 * **The stage buckets are the calendar's, from strengthByStage in
 * app/src/lib/dayMarks.ts**: confirmed, completed and closed are filled;
 * provisional is a ring; possible and quoted are the count badge; new,
 * in_conversation, lost and cancelled carry nothing. Taken from that table
 * rather than reasoned out again here, or the list and the calendar could
 * describe the same Saturday differently, which is the exact failure these
 * counts exist to prevent.
 */
describe('the clash counts', function () {
    it('reports what is already on the date', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $date = today()->addDays(279)->toDateString();

        eventOn($date, ['stage' => BookingStage::Confirmed]);
        enquiry(BookingStage::Possible, $date);
        enquiry(BookingStage::Quoted, $date);
        $subject = enquiry(BookingStage::Possible, $date, ['last_touched_at' => now()->subDays(40)]);

        currentAccount()->clear();

        $response = $this->actingAs($user)->getJson('/api/enquiries')->assertOk();

        $row = collect($response->json('data'))->firstWhere('id', $subject->id);

        expect(array_keys($row['clash']))->toBe(CLASH_KEYS)
            ->and($row['clash'])->toBe(['confirmed' => 1, 'provisional' => 0, 'others' => 2]);
    });

    // The deliberate departure from the calendar's rule. An enquiry at
    // in_conversation carries no mark of its own, because business logic 5.1
    // puts the soft hold at Possible, and it still reports what is already
    // there: the counts describe the date, not this record's contribution to
    // it.
    it('reports the same counts on an enquiry whose own stage holds no date', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $date = today()->addDays(279)->toDateString();

        eventOn($date, ['stage' => BookingStage::Confirmed]);
        enquiry(BookingStage::Possible, $date);
        enquiry(BookingStage::Quoted, $date);
        $subject = enquiry(BookingStage::InConversation, $date, ['last_touched_at' => now()->subDays(40)]);

        currentAccount()->clear();

        $row = collect($this->actingAs($user)->getJson('/api/enquiries')->assertOk()->json('data'))
            ->firstWhere('id', $subject->id);

        expect($row['clash'])->toBe(['confirmed' => 1, 'provisional' => 0, 'others' => 2]);
    });

    // Lost has released the date, so counting what else wants it would be
    // describing a contest it has withdrawn from.
    it('is null on a lost enquiry however busy its date is', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $date = today()->addDays(279)->toDateString();

        eventOn($date, ['stage' => BookingStage::Confirmed]);
        enquiry(BookingStage::Possible, $date);
        $subject = enquiry(BookingStage::Lost, $date, ['lost_reason' => LostReason::AlreadyBooked]);

        currentAccount()->clear();

        $row = collect($this->actingAs($user)->getJson('/api/enquiries')->assertOk()->json('data'))
            ->firstWhere('id', $subject->id);

        expect($row['clash'])->toBeNull();
    });

    // Paired with the first test in this block, so a clash that reports 1/0/2
    // cannot be an endpoint that counts the whole account: a date with nothing
    // else on it has to come back null.
    it('is null on a date carrying nothing else, and never counts the row itself', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        enquiry(BookingStage::Possible, today()->addDays(279)->toDateString());

        currentAccount()->clear();

        $this->actingAs($user)
            ->getJson('/api/enquiries')
            ->assertOk()
            ->assertJsonPath('data.0.clash', null);
    });

    // The stages that carry nothing on the calendar carry nothing here either,
    // and cancelled in particular has to vanish or the artist is warned off a
    // day she is free to work.
    it('ignores the stages that hold no date', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $date = today()->addDays(279)->toDateString();

        enquiry(BookingStage::New, $date);
        enquiry(BookingStage::InConversation, $date);
        enquiry(BookingStage::Lost, $date);
        eventOn($date, ['stage' => BookingStage::Cancelled]);
        $subject = enquiry(BookingStage::Possible, $date, ['last_touched_at' => now()->subDays(40)]);

        currentAccount()->clear();

        $row = collect($this->actingAs($user)->getJson('/api/enquiries')->assertOk()->json('data'))
            ->firstWhere('id', $subject->id);

        expect($row['clash'])->toBeNull();
    });

    /**
     * Completed and closed are filled marks, not past work.
     *
     * "Completed is behind us" is nearly always true and enforced by nothing:
     * a booking can be marked completed with its date still ahead. The
     * calendar's table is what settles it, and this is the assertion that
     * keeps the two screens saying the same thing about the same Saturday.
     */
    it('counts completed and closed as taken, the way the calendar does', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $date = today()->addDays(279)->toDateString();

        eventOn($date, ['stage' => BookingStage::Completed]);
        eventOn($date, ['stage' => BookingStage::Closed]);
        eventOn($date, ['stage' => BookingStage::Provisional]);
        $subject = enquiry(BookingStage::Possible, $date, ['last_touched_at' => now()->subDays(40)]);

        currentAccount()->clear();

        $row = collect($this->actingAs($user)->getJson('/api/enquiries')->assertOk()->json('data'))
            ->firstWhere('id', $subject->id);

        expect($row['clash'])->toBe(['confirmed' => 2, 'provisional' => 1, 'others' => 0]);
    });

    // A booking with a trial and the wedding on one date is one thing on that
    // date, not two. The counts are of bookings, not of events.
    it('counts a booking once however many of its events fall on the date', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $date = today()->addDays(279)->toDateString();

        $twice = enquiry(BookingStage::Quoted, $date, [], ['type' => 'main']);
        Event::factory()->create([
            'booking_id' => $twice->id,
            'type' => 'trial',
            'event_date' => $date,
        ]);

        $subject = enquiry(BookingStage::Possible, $date, ['last_touched_at' => now()->subDays(40)]);

        currentAccount()->clear();

        $row = collect($this->actingAs($user)->getJson('/api/enquiries')->assertOk()->json('data'))
            ->firstWhere('id', $subject->id);

        expect($row['clash'])->toBe(['confirmed' => 0, 'provisional' => 0, 'others' => 1]);
    });

    // The limitation of a one-date row, asserted so it is a decision somebody
    // can read rather than a surprise: the row is shown by its main day, so it
    // is the main day that is checked for a clash. A trial-date clash is the
    // calendar's job.
    it('checks the date the row is shown by, and not the enquiry\'s other dates', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $trialDate = today()->addDays(189)->toDateString();
        $weddingDate = today()->addDays(265)->toDateString();

        eventOn($trialDate, ['stage' => BookingStage::Confirmed]);

        $subject = enquiry(BookingStage::Possible, $weddingDate, ['last_touched_at' => now()->subDays(40)], ['type' => 'main']);
        Event::factory()->create([
            'booking_id' => $subject->id,
            'type' => 'trial',
            'event_date' => $trialDate,
        ]);

        currentAccount()->clear();

        $row = collect($this->actingAs($user)->getJson('/api/enquiries')->assertOk()->json('data'))
            ->firstWhere('id', $subject->id);

        expect($row['event']['date'])->toBe($weddingDate)
            ->and($row['clash'])->toBeNull();
    });
});

describe('how an enquiry ended', function () {
    it('sends the reason and the side it came from', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        // One from each group. The two endings behave identically and the only
        // thing that differs is who decided.
        $theirs = enquiry(BookingStage::Lost, null, [
            'last_touched_at' => now()->subDays(40),
            'lost_reason' => LostReason::NoReply,
        ]);
        $mine = enquiry(BookingStage::Lost, null, [
            'last_touched_at' => now()->subDays(28),
            'lost_reason' => LostReason::AlreadyBooked,
        ]);

        currentAccount()->clear();

        $rows = collect($this->actingAs($user)->getJson('/api/enquiries')->assertOk()->json('data'))
            ->keyBy('id');

        expect($rows[$theirs->id]['lost_reason'])->toBe('no_reply')
            ->and($rows[$theirs->id]['lost_side'])->toBe('client')
            ->and($rows[$mine->id]['lost_reason'])->toBe('already_booked')
            ->and($rows[$mine->id]['lost_side'])->toBe('artist');
    });

    // Paired with the test above, so "sends the reason" cannot pass on a
    // payload that sends a reason for everything.
    it('sends null for both on an enquiry that has not ended', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        enquiry(BookingStage::Quoted, null);

        currentAccount()->clear();

        $this->actingAs($user)
            ->getJson('/api/enquiries')
            ->assertOk()
            ->assertJsonPath('data.0.lost_reason', null)
            ->assertJsonPath('data.0.lost_side', null);
    });
});

describe('the ceiling', function () {
    it('returns everything and says so when under the limit', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        foreach (range(1, 3) as $days) {
            enquiry(BookingStage::Possible, null, ['last_touched_at' => now()->subDays($days)]);
        }

        currentAccount()->clear();

        $this->actingAs($user)
            ->getJson('/api/enquiries')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.returned', 3)
            ->assertJsonPath('meta.truncated', false);
    });

    /**
     * Over the limit it is a flag rather than a 422, for the reason at the top
     * of config/bookings.php: a caller that sends no parameters cannot ask for
     * less, and a refusal would leave that account with a dead screen.
     *
     * The cap is config, so the test moves it rather than creating five
     * hundred rows to reach it. What is under test is the flag, the total and
     * which rows survive, not the number itself.
     */
    it('truncates to the most neglected and reports the real total', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        config(['bookings.max_enquiries' => 2]);

        $unlooked = enquiry(BookingStage::New, null, ['last_touched_at' => now()->subHour()]);
        $stalest = enquiry(BookingStage::Possible, null, ['last_touched_at' => now()->subDays(34)]);
        enquiry(BookingStage::Quoted, null, ['last_touched_at' => now()->subDays(6)]);
        enquiry(BookingStage::Lost, null, ['last_touched_at' => now()->subDays(90)]);

        currentAccount()->clear();

        $response = $this->actingAs($user)->getJson('/api/enquiries')->assertOk();

        $response
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('meta.returned', 2)
            ->assertJsonPath('meta.truncated', true);

        // The useful end of the list: the one nobody has looked at and the one
        // nobody has touched for longest. The archive is what goes, even though
        // it holds the oldest timestamp of the four, which is what makes
        // truncation survivable.
        expect($response->json('data.*.id'))->toBe([$unlooked->id, $stalest->id]);
    });
});

/**
 * The failure this guards against is invisible in a demo database and ruinous
 * in a real one: without eager loading the endpoint issues a query per enquiry
 * per relation, and it grows with the list rather than with the request.
 */
it('issues the same number of queries however many enquiries there are', function () {
    $user = bookingsOwner();
    $account = $user->accounts()->first();

    $count = function (int $enquiries) use ($user, $account) {
        currentAccount()->set($account);

        Event::query()->delete();
        Payment::query()->delete();
        Invoice::query()->delete();
        Booking::query()->forceDelete();
        Contact::query()->forceDelete();

        for ($i = 0; $i < $enquiries; $i++) {
            $booking = enquiry(BookingStage::Quoted, today()->addDays($i + 1)->toDateString());
            BookingLine::factory()->create(['booking_id' => $booking->id, 'quantity' => 2, 'unit_price_minor' => 6500]);
            $invoice = Invoice::factory()->issued($i + 1)->create([
                'booking_id' => $booking->id,
                'total_minor' => 45000,
                'deposit_minor' => 0,
                // Settled and past its due date, so the resolver reads every
                // invoice rather than returning on the first one waiting.
                'balance_due_on' => today()->subDays(3),
            ]);
            Payment::factory()->create([
                'invoice_id' => $invoice->id,
                'booking_id' => $booking->id,
                'amount_minor' => 45000,
            ]);
        }

        currentAccount()->clear();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($user)->getJson('/api/enquiries')->assertOk();

        $queries = count(DB::getQueryLog());

        DB::disableQueryLog();

        return $queries;
    };

    expect($count(3))->toBe($count(30));
});

/**
 * The clash trap, and it is a different trap from the one above.
 *
 * Counting per row grows with the number of DISTINCT DATES rather than with
 * the number of rows, so it survives every test written against a handful of
 * enquiries sharing one Saturday: three rows on one date is one query per row
 * and one query for the date look identical. Thirty dates is what tells them
 * apart.
 */
it('issues the same number of queries however many distinct dates there are', function () {
    $user = bookingsOwner();
    $account = $user->accounts()->first();

    $count = function (int $dates) use ($user, $account) {
        currentAccount()->set($account);

        Event::query()->delete();
        Booking::query()->forceDelete();
        Contact::query()->forceDelete();

        for ($i = 0; $i < $dates; $i++) {
            // Two enquiries a date, so every date has something on it to count
            // and the clash query has real work to do.
            enquiry(BookingStage::Possible, today()->addDays($i + 1)->toDateString());
            enquiry(BookingStage::Quoted, today()->addDays($i + 1)->toDateString());
        }

        currentAccount()->clear();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($user)->getJson('/api/enquiries')->assertOk();

        $queries = count(DB::getQueryLog());

        DB::disableQueryLog();

        return $queries;
    };

    expect($count(2))->toBe($count(30));
});
