<?php

use App\Enums\BookingStage;
use App\Models\Booking;
use App\Models\Contact;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * The keys GET /api/contacts promises, mirroring app/src/types/contacts.ts.
 *
 * That file is the contract and these lists are its twin. If one moves without
 * the other, the app reads undefined from a field the API renamed and nothing
 * fails until somebody opens the screen, so the two are pinned together here on
 * purpose.
 */
const CONTACT_KEYS = [
    'id',
    'first_name',
    'last_name',
    'email',
    'phone',
    'address_line_1',
    'address_line_2',
    'city',
    'postcode',
    'country',
    'bookings',
    'booking_count',
    'next_booking',
    'last_booking',
    'outstanding',
];

const CONTACT_BOOKING_KEYS = [
    'id',
    'event_type',
    'date',
    'venue_name',
    'city',
    'stage',
    'total_minor',
    'currency',
];

/**
 * The outstanding entry deliberately does NOT match the OutstandingAmount in
 * app/src/types/contacts.ts as that file stands: the API says amount_minor
 * rather than minor, is_overdue rather than overdue, and adds
 * is_account_currency, which the front end has no equivalent of. The type file
 * is what changes, in the prompt that swaps the screen onto this endpoint.
 * Recorded here so the difference is a decision somebody can read rather than
 * a surprise.
 */
const OUTSTANDING_KEYS = [
    'currency',
    'amount_minor',
    'is_overdue',
    'is_account_currency',
];

/**
 * A second booking on an existing contact, with one event on the given date.
 */
function bookingFor(Contact $contact, string $date, array $eventAttributes = []): Booking
{
    $booking = Booking::factory()->create([
        'contact_id' => $contact->id,
        'stage' => BookingStage::Confirmed,
    ]);

    Event::factory()->create($eventAttributes + [
        'booking_id' => $booking->id,
        'event_date' => $date,
    ]);

    return $booking;
}

/**
 * An issued invoice on the contact's first booking. Deposit zero, so a test
 * about a balance is not also a test about a deposit.
 */
function issuedInvoiceFor(Contact $contact, int $totalMinor): Invoice
{
    // invoices is unique on (account_id, sequence), so a test creating more
    // than one issued invoice needs them to differ. The counter is per test
    // run and the numbers themselves mean nothing here.
    static $sequence = 0;

    return Invoice::factory()->issued(++$sequence)->create([
        'booking_id' => $contact->bookings()->first()->id,
        'total_minor' => $totalMinor,
        'deposit_minor' => 0,
    ]);
}

function payTowards(Invoice $invoice, int $amountMinor): Payment
{
    return Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'booking_id' => $invoice->booking_id,
        'amount_minor' => $amountMinor,
    ]);
}

it('returns every contact with every key the app expects', function () {
    $user = bookingsOwner();
    currentAccount()->set($user->accounts()->first());

    contactWithBooking(
        today()->addDays(30)->toDateString(),
        ['first_name' => 'Imogen', 'last_name' => 'Hartwell', 'city' => 'Hertford'],
        [],
        ['venue_name' => 'Ashgrove Manor', 'city' => 'Hertford'],
    );

    currentAccount()->clear();

    $response = $this->actingAs($user)->getJson('/api/contacts')->assertOk();

    expect(array_keys($response->json('data.0')))->toBe(CONTACT_KEYS)
        ->and(array_keys($response->json('data.0.bookings.0')))->toBe(CONTACT_BOOKING_KEYS)
        ->and(array_keys($response->json('data.0.next_booking')))->toBe(CONTACT_BOOKING_KEYS);

    $response
        ->assertJsonPath('data.0.first_name', 'Imogen')
        ->assertJsonPath('data.0.last_name', 'Hartwell')
        ->assertJsonPath('data.0.booking_count', 1)
        ->assertJsonPath('data.0.bookings.0.venue_name', 'Ashgrove Manor')
        ->assertJsonPath('data.0.bookings.0.event_type', 'main')
        ->assertJsonPath('data.0.bookings.0.stage', 'confirmed')
        ->assertJsonPath('data.0.bookings.0.currency', 'GBP')
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('meta.returned', 1)
        ->assertJsonPath('meta.truncated', false);
});

it('sends the date as a local calendar date, never an instant', function () {
    $user = bookingsOwner();
    currentAccount()->set($user->accounts()->first());

    // Late in the evening is where a timezone conversion would show: sent as an
    // instant in UTC this would arrive as the following day for the eight
    // months the clocks are forward.
    contactWithBooking('2027-06-14', [], [], ['start_time' => '23:30:00']);

    currentAccount()->clear();

    $this->actingAs($user)
        ->getJson('/api/contacts')
        ->assertOk()
        ->assertJsonPath('data.0.next_booking.date', '2027-06-14');
});

describe('tenancy', function () {
    it('never shows another account\'s contacts', function () {
        $mine = bookingsOwner();
        $theirs = bookingsOwner();

        currentAccount()->set($theirs->accounts()->first());
        contactWithBooking(today()->addDays(5)->toDateString(), ['first_name' => 'Theirs']);

        currentAccount()->set($mine->accounts()->first());
        contactWithBooking(today()->addDays(6)->toDateString(), ['first_name' => 'Mine']);

        currentAccount()->clear();

        $this->actingAs($mine)
            ->getJson('/api/contacts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.first_name', 'Mine');
    });

    /**
     * The one that matters, and the one a where('account_id', ...) written by
     * hand would pass while getting wrong.
     *
     * Two accounts share nothing, but a contact row and a booking row are
     * separate scopes: an endpoint that scoped the contacts and then loaded
     * bookings through an unscoped relation would return my contact carrying
     * somebody else's work, and the count, the dates and the money would all be
     * wrong in a way no list-level assertion catches.
     */
    it('never shows another account\'s bookings inside a contact that is mine', function () {
        $mine = bookingsOwner();
        $theirs = bookingsOwner();

        currentAccount()->set($mine->accounts()->first());
        $contact = contactWithBooking(today()->addDays(6)->toDateString());

        // A booking on the other account pointed at my contact's id. Nothing in
        // the schema stops the row existing; the scope is what stops it being
        // read back.
        currentAccount()->set($theirs->accounts()->first());
        $intruder = Booking::factory()->create([
            'contact_id' => $contact->id,
            'stage' => BookingStage::Confirmed,
        ]);
        Event::factory()->create([
            'booking_id' => $intruder->id,
            'event_date' => today()->addDays(2)->toDateString(),
        ]);

        currentAccount()->clear();

        $response = $this->actingAs($mine)->getJson('/api/contacts')->assertOk();

        expect($response->json('data.0.booking_count'))->toBe(1)
            ->and($response->json('data.0.bookings'))->toHaveCount(1)
            // The intruder's event is sooner, so if it leaked it would be the
            // next booking rather than merely an extra row.
            ->and($response->json('data.0.next_booking.date'))->toBe(today()->addDays(6)->toDateString());
    });
});

// The screen has a real case for this: an enquiry that never became a booking,
// and a contact typed in from a card at a wedding fair.
it('gives a contact with no bookings a zero count, two nulls and an empty array', function () {
    $user = bookingsOwner();
    currentAccount()->set($user->accounts()->first());

    Contact::factory()->create(['first_name' => 'Fenella']);

    currentAccount()->clear();

    $this->actingAs($user)
        ->getJson('/api/contacts')
        ->assertOk()
        ->assertJsonPath('data.0.booking_count', 0)
        ->assertJsonPath('data.0.bookings', [])
        ->assertJsonPath('data.0.next_booking', null)
        ->assertJsonPath('data.0.last_booking', null)
        ->assertJsonPath('data.0.outstanding', []);
});

describe('next and last', function () {
    it('takes the soonest future and the most recent past', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $contact = contactWithBooking(today()->addDays(60)->toDateString());
        bookingFor($contact, today()->addDays(20)->toDateString());
        bookingFor($contact, today()->subDays(10)->toDateString());
        bookingFor($contact, today()->subDays(400)->toDateString());

        currentAccount()->clear();

        $this->actingAs($user)
            ->getJson('/api/contacts')
            ->assertOk()
            ->assertJsonPath('data.0.next_booking.date', today()->addDays(20)->toDateString())
            ->assertJsonPath('data.0.last_booking.date', today()->subDays(10)->toDateString())
            ->assertJsonPath('data.0.booking_count', 4);
    });

    it('gives a contact with only history a null next', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        contactWithBooking(today()->subDays(30)->toDateString());

        currentAccount()->clear();

        $this->actingAs($user)
            ->getJson('/api/contacts')
            ->assertOk()
            ->assertJsonPath('data.0.next_booking', null)
            ->assertJsonPath('data.0.last_booking.date', today()->subDays(30)->toDateString());
    });

    it('gives a contact with only work ahead a null last', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        contactWithBooking(today()->addDays(30)->toDateString());

        currentAccount()->clear();

        $this->actingAs($user)
            ->getJson('/api/contacts')
            ->assertOk()
            ->assertJsonPath('data.0.next_booking.date', today()->addDays(30)->toDateString())
            ->assertJsonPath('data.0.last_booking', null);
    });

    /**
     * The rule that decides which event a field carries, and the reason the
     * three uses are not the same.
     *
     * bookings[] shows the main day, because a list of somebody's work is a
     * list of the jobs. next_booking shows the soonest event of ANY type,
     * because that field answers "when do I next see this person", and on
     * 1 August that is the trial on the 15th rather than the wedding in
     * September. Taking the main event everywhere would hide every trial from
     * the one field that exists to tell you about the next appointment.
     */
    it('shows the trial as next and the wedding in the list', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $contact = Contact::factory()->create();
        $booking = Booking::factory()->create([
            'contact_id' => $contact->id,
            'stage' => BookingStage::Confirmed,
        ]);
        Event::factory()->create([
            'booking_id' => $booking->id,
            'type' => 'trial',
            'event_date' => today()->addDays(14)->toDateString(),
        ]);
        Event::factory()->create([
            'booking_id' => $booking->id,
            'type' => 'main',
            'event_date' => today()->addDays(60)->toDateString(),
        ]);

        currentAccount()->clear();

        $this->actingAs($user)
            ->getJson('/api/contacts')
            ->assertOk()
            // One booking, one row, showing the wedding.
            ->assertJsonCount(1, 'data.0.bookings')
            ->assertJsonPath('data.0.bookings.0.event_type', 'main')
            ->assertJsonPath('data.0.bookings.0.date', today()->addDays(60)->toDateString())
            // The next appointment is the trial.
            ->assertJsonPath('data.0.next_booking.event_type', 'trial')
            ->assertJsonPath('data.0.next_booking.date', today()->addDays(14)->toDateString())
            // Both are the same booking, and id means a booking throughout.
            ->assertJsonPath('data.0.next_booking.id', $booking->id)
            ->assertJsonPath('data.0.bookings.0.id', $booking->id);
    });

    // A standalone trial or a commercial shoot is a booking with no main day.
    // Showing it with no date at all would be worse than showing the date it
    // does have.
    it('falls back to the earliest event when a booking has no main day', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        contactWithBooking(today()->addDays(20)->toDateString(), [], [], ['type' => 'shoot']);

        currentAccount()->clear();

        $this->actingAs($user)
            ->getJson('/api/contacts')
            ->assertOk()
            ->assertJsonPath('data.0.bookings.0.event_type', 'shoot')
            ->assertJsonPath('data.0.bookings.0.date', today()->addDays(20)->toDateString());
    });
});

describe('outstanding money', function () {
    it('is what an issued invoice is owed less what has been paid', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $contact = contactWithBooking(today()->addDays(30)->toDateString());
        $invoice = issuedInvoiceFor($contact, 45000);
        payTowards($invoice, 11250);

        currentAccount()->clear();

        $response = $this->actingAs($user)->getJson('/api/contacts')->assertOk();

        expect(array_keys($response->json('data.0.outstanding.0')))->toBe(OUTSTANDING_KEYS);

        $response
            ->assertJsonCount(1, 'data.0.outstanding')
            ->assertJsonPath('data.0.outstanding.0.currency', 'GBP')
            ->assertJsonPath('data.0.outstanding.0.is_overdue', false)
            ->assertJsonPath('data.0.outstanding.0.amount_minor', 33750)
            ->assertJsonPath('data.0.outstanding.0.is_account_currency', true);
    });

    it('ignores a draft invoice, which has no money on it until issue', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $contact = contactWithBooking(today()->addDays(30)->toDateString());
        Invoice::factory()->create([
            'booking_id' => $contact->bookings()->first()->id,
            'total_minor' => 45000,
        ]);

        currentAccount()->clear();

        $this->actingAs($user)
            ->getJson('/api/contacts')
            ->assertOk()
            ->assertJsonPath('data.0.outstanding', []);
    });

    it('ignores a void invoice', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $contact = contactWithBooking(today()->addDays(30)->toDateString());
        issuedInvoiceFor($contact, 45000)->forceFill([
            'status' => 'void',
            'voided_at' => now(),
        ])->save();

        currentAccount()->clear();

        $this->actingAs($user)
            ->getJson('/api/contacts')
            ->assertOk()
            ->assertJsonPath('data.0.outstanding', []);
    });

    it('returns an empty array when everything is paid', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $contact = contactWithBooking(today()->addDays(30)->toDateString());
        payTowards(issuedInvoiceFor($contact, 45000), 45000);

        currentAccount()->clear();

        $this->actingAs($user)
            ->getJson('/api/contacts')
            ->assertOk()
            ->assertJsonPath('data.0.outstanding', []);
    });

    it('marks an invoice past its balance date overdue', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $contact = contactWithBooking(today()->addDays(30)->toDateString());
        issuedInvoiceFor($contact, 45000)->forceFill([
            'balance_due_on' => today()->subDay(),
        ])->save();

        currentAccount()->clear();

        $this->actingAs($user)
            ->getJson('/api/contacts')
            ->assertOk()
            ->assertJsonPath('data.0.outstanding.0.is_overdue', true);
    });

    it('does not mark a snoozed invoice overdue', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $contact = contactWithBooking(today()->addDays(30)->toDateString());
        issuedInvoiceFor($contact, 45000)->forceFill([
            'balance_due_on' => today()->subDay(),
            'reminders_snoozed_until' => today()->addWeek(),
        ])->save();

        currentAccount()->clear();

        $this->actingAs($user)
            ->getJson('/api/contacts')
            ->assertOk()
            ->assertJsonPath('data.0.outstanding.0.is_overdue', false)
            // Paired with the assertion above, so "snoozed is not overdue"
            // cannot pass because the amount vanished: it is still owed.
            ->assertJsonPath('data.0.outstanding.0.amount_minor', 45000);
    });

    /**
     * Two currencies, and the field that keeps the array honest.
     *
     * Nothing may depend on the order of this array. is_account_currency is how
     * the client finds the entry it wants, so the assertions here read the
     * entries by their currency rather than by their position, which is also
     * what proves the field is doing its job.
     */
    it('returns one entry per currency, each saying whether it is the account\'s', function () {
        $user = bookingsOwner();
        $account = $user->accounts()->first();
        currentAccount()->set($account);

        $contact = contactWithBooking(today()->addDays(30)->toDateString());
        issuedInvoiceFor($contact, 45000);

        $abroad = Booking::factory()->create([
            'contact_id' => $contact->id,
            'stage' => BookingStage::Confirmed,
            'currency' => 'EUR',
        ]);
        Event::factory()->create([
            'booking_id' => $abroad->id,
            'event_date' => today()->addDays(90)->toDateString(),
        ]);
        Invoice::factory()->issued(2)->create([
            'booking_id' => $abroad->id,
            'currency' => 'EUR',
            'total_minor' => 60000,
            'deposit_minor' => 0,
        ]);

        currentAccount()->clear();

        $owed = collect($this->actingAs($user)->getJson('/api/contacts')->assertOk()->json('data.0.outstanding'))
            ->keyBy('currency');

        expect($owed)->toHaveCount(2)
            ->and($owed['GBP']['amount_minor'])->toBe(45000)
            ->and($owed['GBP']['is_account_currency'])->toBeTrue()
            ->and($owed['EUR']['amount_minor'])->toBe(60000)
            ->and($owed['EUR']['is_account_currency'])->toBeFalse()
            ->and($account->currency)->toBe('GBP');
    });

    // The order is cosmetic and the same twice running, which is all that is
    // asked of it. If this ever needs changing for an unrelated reason, nothing
    // about correctness moves with it, which is the point of the flag above.
    it('sorts the currencies the same way twice running', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $contact = contactWithBooking(today()->addDays(30)->toDateString());
        issuedInvoiceFor($contact, 45000);

        foreach (['USD', 'EUR'] as $index => $currency) {
            $booking = Booking::factory()->create([
                'contact_id' => $contact->id,
                'stage' => BookingStage::Confirmed,
                'currency' => $currency,
            ]);
            Invoice::factory()->issued($index + 2)->create([
                'booking_id' => $booking->id,
                'currency' => $currency,
                'total_minor' => 10000,
                'deposit_minor' => 0,
            ]);
        }

        currentAccount()->clear();

        $first = $this->actingAs($user)->getJson('/api/contacts')->json('data.0.outstanding.*.currency');
        $again = $this->actingAs($user)->getJson('/api/contacts')->json('data.0.outstanding.*.currency');

        expect($first)->toBe($again)->toBe(['EUR', 'GBP', 'USD']);
    });
});

describe('the order', function () {
    /**
     * Work ahead of you first and soonest first, then history newest first,
     * then everybody with neither.
     *
     * It is not the arbitrary "activity descending" it could have been: this is
     * what makes the ceiling survivable, because a truncated response is then
     * the useful end of the list rather than a slice of it, and it is the order
     * the screen would put them in anyway.
     */
    it('puts work ahead first and soonest first, then history newest first', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $soon = contactWithBooking(today()->addDays(5)->toDateString(), ['first_name' => 'Soon']);
        $later = contactWithBooking(today()->addDays(200)->toDateString(), ['first_name' => 'Later']);
        $recent = contactWithBooking(today()->subDays(10)->toDateString(), ['first_name' => 'Recent']);
        $old = contactWithBooking(today()->subDays(500)->toDateString(), ['first_name' => 'Old']);
        $never = Contact::factory()->create(['first_name' => 'Never']);

        currentAccount()->clear();

        $ids = $this->actingAs($user)->getJson('/api/contacts')->assertOk()->json('data.*.id');

        expect($ids)->toBe([$soon->id, $later->id, $recent->id, $old->id, $never->id]);
    });

    /**
     * The anti-drift test for the one thing in this endpoint that is written
     * twice.
     *
     * ContactController::ordered() says next-and-last in SQL, because ordering
     * has to happen before the limit. ContactActivity says it again in PHP,
     * because that is what fills the fields. This asserts they agree: the order
     * the server sent is the order you get by sorting the payload's own
     * next_booking and last_booking the same way.
     */
    it('orders by the same rule the payload reports', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        contactWithBooking(today()->addDays(5)->toDateString());
        contactWithBooking(today()->addDays(200)->toDateString());
        contactWithBooking(today()->subDays(10)->toDateString());
        contactWithBooking(today()->subDays(500)->toDateString());
        Contact::factory()->create();

        currentAccount()->clear();

        $data = collect($this->actingAs($user)->getJson('/api/contacts')->assertOk()->json('data'));

        $expected = $data
            ->sortBy(fn (array $contact) => [
                // Ascending, nulls last: work ahead of you first and soonest
                // first, then everybody with none.
                $contact['next_booking']['date'] ?? '9999-12-31',
                // Descending, nulls last. Sorting ascending on the negated
                // timestamp is what makes one pass do both, and PHP_INT_MAX is
                // what puts a contact with no history below one that has some.
                $contact['last_booking'] === null
                    ? PHP_INT_MAX
                    : -strtotime($contact['last_booking']['date']),
                $contact['id'],
            ])
            ->pluck('id')
            ->values()
            ->all();

        expect($data->pluck('id')->all())->toBe($expected);
    });

    it('returns the same order twice running', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        // Four contacts with nothing to separate them but their ids, which is
        // the case an incomplete ordering gets wrong.
        Contact::factory()->count(4)->create();

        currentAccount()->clear();

        $first = $this->actingAs($user)->getJson('/api/contacts')->json('data.*.id');
        $again = $this->actingAs($user)->getJson('/api/contacts')->json('data.*.id');

        expect($first)->toBe($again);
    });
});

// Somebody who books under one name. Every part of the screen files them under
// that name rather than under a dash, and that starts with the API sending null
// rather than an empty string.
it('serialises and orders a contact with no last name', function () {
    $user = bookingsOwner();
    currentAccount()->set($user->accounts()->first());

    contactWithBooking(today()->addDays(9)->toDateString(), ['first_name' => 'Anouk', 'last_name' => null]);
    contactWithBooking(today()->addDays(3)->toDateString(), ['first_name' => 'Delphine', 'last_name' => null]);

    currentAccount()->clear();

    $this->actingAs($user)
        ->getJson('/api/contacts')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.first_name', 'Delphine')
        ->assertJsonPath('data.0.last_name', null)
        ->assertJsonPath('data.1.last_name', null);
});

describe('the ceiling', function () {
    it('returns everything and says so when under the limit', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        Contact::factory()->count(3)->create();

        currentAccount()->clear();

        $this->actingAs($user)
            ->getJson('/api/contacts')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.returned', 3)
            ->assertJsonPath('meta.truncated', false);
    });

    /**
     * Over the limit it is a flag rather than a 422, because a caller that
     * sends no parameters cannot ask for less and a refusal would leave that
     * account with a dead screen.
     *
     * The cap is config, so the test moves it rather than creating a thousand
     * rows to reach it. What is under test is the flag, the total and which
     * rows survive, not the number itself.
     */
    it('truncates to the most recently active and reports the real total', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        config(['contacts.max_contacts' => 2]);

        $soon = contactWithBooking(today()->addDays(2)->toDateString(), ['first_name' => 'Soon']);
        $later = contactWithBooking(today()->addDays(40)->toDateString(), ['first_name' => 'Later']);
        contactWithBooking(today()->subDays(5)->toDateString(), ['first_name' => 'Past']);
        Contact::factory()->create(['first_name' => 'Never']);

        currentAccount()->clear();

        $response = $this->actingAs($user)->getJson('/api/contacts')->assertOk();

        $response
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('meta.returned', 2)
            ->assertJsonPath('meta.truncated', true);

        // The useful end of the list: the two with work ahead of them, soonest
        // first, rather than an arbitrary two.
        expect($response->json('data.*.id'))->toBe([$soon->id, $later->id]);
    });
});

/**
 * The failure this guards against is invisible in a demo database and ruinous
 * in a real one: without eager loading the endpoint issues a query per contact
 * per relation, and it grows with the address book rather than with the
 * request.
 */
it('issues the same number of queries however many contacts there are', function () {
    $user = bookingsOwner();
    $account = $user->accounts()->first();

    $count = function (int $contacts) use ($user, $account) {
        currentAccount()->set($account);

        Event::query()->delete();
        Payment::query()->delete();
        Invoice::query()->delete();
        Booking::query()->forceDelete();
        Contact::query()->forceDelete();

        for ($i = 0; $i < $contacts; $i++) {
            $contact = contactWithBooking(today()->addDays($i + 1)->toDateString());
            payTowards(issuedInvoiceFor($contact, 45000), 10000);
        }

        currentAccount()->clear();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($user)->getJson('/api/contacts')->assertOk();

        $queries = count(DB::getQueryLog());

        DB::disableQueryLog();

        return $queries;
    };

    expect($count(3))->toBe($count(30));
});

/**
 * What the ordering actually plans, asserted rather than assumed, and by index
 * name rather than by the plan's shape, because that output changes between
 * Postgres versions.
 *
 * **What this can and cannot claim, because the difference matters.** Nothing
 * indexes the sort itself. The sort key is a correlated subquery, so Postgres
 * computes it once per contact and then sorts the results, and no index in
 * section 9 or anywhere else would change that: the second assertion below
 * pins that down deliberately, so nobody reads the first one as a promise the
 * ordering is index-served.
 *
 * What is indexed is the lookup inside each subquery, which is the part that
 * decides whether the per-contact cost is three page reads or a table scan.
 * Those are bookings (account_id, contact_id) and events (booking_id), both
 * already in section 9, and both are what this asserts.
 *
 * The row counts are not decoration. Against a handful of rows Postgres
 * sequentially scans everything whatever the indexes say, and an earlier
 * version of this test passed on the sort assertion while proving nothing
 * about the lookups, because with one booking in the table it had chosen a
 * sequential scan there too.
 */
it('uses the existing indexes for the activity lookup, and sorts without one', function () {
    $user = bookingsOwner();
    currentAccount()->set($user->accounts()->first());
    $accountId = currentAccount()->id();

    // Bulk inserted rather than made through the factories: what is under test
    // is the planner's choice against realistic table sizes, and four hundred
    // contacts with three bookings each through Eloquent is a slow way to say
    // the same thing.
    $rows = [];
    for ($i = 0; $i < 400; $i++) {
        $rows[] = ['account_id' => $accountId, 'first_name' => 'Contact '.$i, 'created_at' => now(), 'updated_at' => now()];
    }
    DB::table('contacts')->insert($rows);

    $rows = [];
    foreach (DB::table('contacts')->where('account_id', $accountId)->pluck('id') as $contactId) {
        for ($b = 0; $b < 3; $b++) {
            $rows[] = [
                'account_id' => $accountId, 'contact_id' => $contactId, 'stage' => 'confirmed',
                'currency' => 'GBP', 'pricing_mode' => 'itemised', 'feature_overrides' => '{}',
                'last_touched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ];
        }
    }
    DB::table('bookings')->insert($rows);

    $rows = [];
    foreach (DB::table('bookings')->where('account_id', $accountId)->pluck('id') as $n => $bookingId) {
        $rows[] = [
            'account_id' => $accountId, 'booking_id' => $bookingId, 'type' => 'main',
            'event_date' => today()->addDays(($n % 800) - 400)->toDateString(),
            'timezone' => 'Europe/London', 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now(),
        ];
    }
    DB::table('events')->insert($rows);

    DB::statement('analyze contacts');
    DB::statement('analyze bookings');
    DB::statement('analyze events');

    $plan = collect(DB::select(
        'explain select contacts.*,
            (select e.event_date from events e join bookings b on b.id = e.booking_id
              where b.contact_id = contacts.id and b.deleted_at is null
                and e.account_id = ? and e.event_date >= ? order by e.event_date limit 1) as next_activity_on
         from contacts where contacts.account_id = ? and contacts.deleted_at is null
         order by next_activity_on asc nulls last, contacts.id limit ?',
        [$accountId, today()->toDateString(), $accountId, config('contacts.max_contacts')],
    ))->pluck('QUERY PLAN')->implode("\n");

    currentAccount()->clear();

    expect($plan)->toContain('bookings_account_id_contact_id_index')
        ->and($plan)->toContain('events_booking_id_index')
        // The honest half. The ordering is a sort over a computed column, and
        // saying so here is what stops the two assertions above being read as
        // a claim the ordering itself is cheap.
        ->and($plan)->toContain('Sort Key: ((SubPlan 1))');
});
