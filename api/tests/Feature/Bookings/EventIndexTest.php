<?php

use App\Enums\DiscountType;
use App\Models\Booking;
use App\Models\BookingLine;
use App\Models\Contact;
use App\Models\Event;
use Illuminate\Support\Facades\DB;

/**
 * The keys GET /api/events promises, mirroring app/src/types/bookings.ts.
 *
 * That file is the contract and this list is its twin. If one moves without
 * the other, the app reads undefined from a field the API renamed and nothing
 * fails until somebody opens the screen, so the two are pinned together here
 * on purpose.
 */
const EVENT_KEYS = [
    'id',
    'booking_id',
    'type',
    'label',
    'date',
    'start_time',
    'venue_name',
    'city',
    'client_name',
    'stage',
    'total_minor',
    'currency',
    'waiting_on',
    'last_touched_at',
];

it('returns the events in the window with every key the app expects', function () {
    $user = bookingsOwner();
    currentAccount()->set($user->accounts()->first());

    $contact = Contact::factory()->create(['first_name' => 'Amelia', 'last_name' => 'Trent']);
    $booking = Booking::factory()->confirmed()->create(['contact_id' => $contact->id]);
    BookingLine::factory()->create(['booking_id' => $booking->id, 'quantity' => 2, 'unit_price_minor' => 6500]);
    Event::factory()->create([
        'booking_id' => $booking->id,
        'event_date' => today()->addDays(10)->toDateString(),
        'start_time' => '06:30:00',
        'venue_name' => 'Ashcombe Barn',
        'city' => 'Ware',
    ]);

    currentAccount()->clear();

    $response = $this->actingAs($user)->getJson('/api/events')->assertOk();

    expect(array_keys($response->json('data.0')))->toBe(EVENT_KEYS);

    $response
        ->assertJsonPath('data.0.client_name', 'Amelia Trent')
        ->assertJsonPath('data.0.stage', 'confirmed')
        ->assertJsonPath('data.0.type', 'main')
        ->assertJsonPath('data.0.total_minor', 13000)
        ->assertJsonPath('data.0.currency', 'GBP')
        ->assertJsonPath('data.0.venue_name', 'Ashcombe Barn')
        ->assertJsonPath('data.0.city', 'Ware')
        // 'HH:mm', not the column's 'HH:mm:ss'.
        ->assertJsonPath('data.0.start_time', '06:30')
        ->assertJsonPath('data.0.date', today()->addDays(10)->toDateString());
});

it('sends the date as a local calendar date, never an instant', function () {
    $user = bookingsOwner();
    currentAccount()->set($user->accounts()->first());

    // Late in the evening is where a timezone conversion would show: sent as
    // an instant in UTC this would arrive as the following day for the eight
    // months the clocks are forward.
    eventOn('2027-06-14', [], ['start_time' => '23:30:00']);

    currentAccount()->clear();

    $this->actingAs($user)
        ->getJson('/api/events?from=2027-01-01')
        ->assertOk()
        ->assertJsonPath('data.0.date', '2027-06-14')
        ->assertJsonPath('data.0.start_time', '23:30');
});

it('never shows another account\'s events', function () {
    $mine = bookingsOwner();
    $theirs = bookingsOwner();

    currentAccount()->set($theirs->accounts()->first());
    eventOn(today()->addDays(5)->toDateString());

    currentAccount()->set($mine->accounts()->first());
    eventOn(today()->addDays(6)->toDateString());

    currentAccount()->clear();

    $this->actingAs($mine)
        ->getJson('/api/events')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

describe('the window', function () {
    it('starts at today when from is not given', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        eventOn(today()->subDay()->toDateString());
        eventOn(today()->toDateString());

        currentAccount()->clear();

        $this->actingAs($user)
            ->getJson('/api/events')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.date', today()->toDateString());
    });

    it('reaches back when an earlier from is asked for', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        eventOn(today()->subDay()->toDateString());
        eventOn(today()->toDateString());

        currentAccount()->clear();

        $this->actingAs($user)
            ->getJson('/api/events?from='.today()->subDays(7)->toDateString())
            ->assertOk()
            ->assertJsonCount(2, 'data');
    });

    // The first call the app makes. "Later" is one of the list's four groups
    // and cannot be computed from a subset, so an omitted `to` has to mean
    // everything forward rather than a default window.
    it('has no upper bound when to is omitted', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        eventOn(today()->addDays(3)->toDateString());
        eventOn(today()->addYear()->toDateString());

        currentAccount()->clear();

        $this->actingAs($user)
            ->getJson('/api/events')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('stops at to when one is given', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        eventOn(today()->addDays(3)->toDateString());
        eventOn(today()->addDays(40)->toDateString());

        currentAccount()->clear();

        $this->actingAs($user)
            ->getJson('/api/events?to='.today()->addDays(10)->toDateString())
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('refuses a range wider than the cap', function () {
        $user = bookingsOwner();

        $this->actingAs($user)
            ->getJson('/api/events?from=2000-01-01&to=2100-01-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors('to')
            ->assertJsonPath('errors.to.0', __('bookings.range_too_wide', [
                'days' => config('bookings.max_span_days'),
            ]));
    });

    it('accepts a range exactly at the cap', function () {
        $user = bookingsOwner();

        $from = today();
        $to = $from->copy()->addDays(config('bookings.max_span_days'));

        $this->actingAs($user)
            ->getJson('/api/events?from='.$from->toDateString().'&to='.$to->toDateString())
            ->assertOk();
    });

    it('refuses a range that runs backwards', function () {
        $user = bookingsOwner();

        $this->actingAs($user)
            ->getJson('/api/events?from='.today()->addDays(10)->toDateString().'&to='.today()->toDateString())
            ->assertStatus(422)
            ->assertJsonValidationErrors('to');
    });

    it('refuses when the range holds more events than the cap allows', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        // The cap is config, so the test moves it rather than creating two
        // thousand rows to reach it. What is under test is that the count
        // refuses before the fetch, not the number itself.
        config(['bookings.max_events' => 2]);

        $booking = Booking::factory()->confirmed()->create();
        Event::factory()->count(3)->create([
            'booking_id' => $booking->id,
            'type' => 'trial',
            'event_date' => today()->addDay(),
        ]);

        currentAccount()->clear();

        $this->actingAs($user)
            ->getJson('/api/events')
            ->assertStatus(422)
            ->assertJsonPath('errors.from.0', __('bookings.too_many_events', ['count' => 2]));
    });
});

// The list renders in exactly the order the API sends and must not sort
// again, so the order has to be total: every tie broken, and the same twice
// running.
it('orders by date, then start time with nulls last, then id', function () {
    $user = bookingsOwner();
    currentAccount()->set($user->accounts()->first());

    $day = today()->addDays(5)->toDateString();
    $later = today()->addDays(6)->toDateString();

    $noTime = eventOn($day, [], ['start_time' => null, 'type' => 'trial']);
    $nextDay = eventOn($later, [], ['start_time' => '06:00:00']);
    $early = eventOn($day, [], ['start_time' => '06:00:00']);
    $late = eventOn($day, [], ['start_time' => '14:00:00', 'type' => 'trial']);
    // Same day and same time as $early, so only the id can separate them.
    $sameTime = eventOn($day, [], ['start_time' => '06:00:00', 'type' => 'trial']);

    currentAccount()->clear();

    $ids = $this->actingAs($user)
        ->getJson('/api/events')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$early->id, $sameTime->id, $late->id, $noTime->id, $nextDay->id]);
});

describe('the total', function () {
    it('sums itemised lines', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $booking = Booking::factory()->confirmed()->create();
        BookingLine::factory()->create(['booking_id' => $booking->id, 'quantity' => 3, 'unit_price_minor' => 6500]);
        BookingLine::factory()->create(['booking_id' => $booking->id, 'quantity' => 1, 'unit_price_minor' => 4000]);
        Event::factory()->create(['booking_id' => $booking->id, 'event_date' => today()->addDay()]);

        currentAccount()->clear();

        $this->actingAs($user)->getJson('/api/events')
            ->assertJsonPath('data.0.total_minor', 23500);
    });

    it('uses the fixed price and ignores the lines in fixed mode', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $booking = Booking::factory()->confirmed()->fixedPrice(90000)->create();
        BookingLine::factory()->create(['booking_id' => $booking->id, 'quantity' => 9, 'unit_price_minor' => 9999]);
        Event::factory()->create(['booking_id' => $booking->id, 'event_date' => today()->addDay()]);

        currentAccount()->clear();

        $this->actingAs($user)->getJson('/api/events')
            ->assertJsonPath('data.0.total_minor', 90000);
    });

    it('takes an amount discount off', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $booking = Booking::factory()->confirmed()->create([
            'discount_type' => DiscountType::Amount,
            'discount_value' => 5000,
        ]);
        BookingLine::factory()->create(['booking_id' => $booking->id, 'quantity' => 2, 'unit_price_minor' => 10000]);
        Event::factory()->create(['booking_id' => $booking->id, 'event_date' => today()->addDay()]);

        currentAccount()->clear();

        $this->actingAs($user)->getJson('/api/events')
            ->assertJsonPath('data.0.total_minor', 15000);
    });

    it('takes a percentage discount off', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $booking = Booking::factory()->confirmed()->create([
            'discount_type' => DiscountType::Percent,
            'discount_value' => 10,
        ]);
        BookingLine::factory()->create(['booking_id' => $booking->id, 'quantity' => 2, 'unit_price_minor' => 10000]);
        Event::factory()->create(['booking_id' => $booking->id, 'event_date' => today()->addDay()]);

        currentAccount()->clear();

        $this->actingAs($user)->getJson('/api/events')
            ->assertJsonPath('data.0.total_minor', 18000);
    });
});

// The failure this guards against is invisible in a demo database and ruinous
// in a real one: without eager loading the endpoint issues a query per event
// per relation, and it grows with the diary rather than with the request.
it('issues the same number of queries however many events there are', function () {
    $user = bookingsOwner();
    $account = $user->accounts()->first();

    $count = function (int $events) use ($user, $account) {
        currentAccount()->set($account);

        Event::query()->delete();
        Booking::query()->forceDelete();

        for ($i = 0; $i < $events; $i++) {
            $booking = Booking::factory()->confirmed()->create();
            BookingLine::factory()->create(['booking_id' => $booking->id]);
            Event::factory()->create([
                'booking_id' => $booking->id,
                'event_date' => today()->addDays($i + 1),
            ]);
        }

        currentAccount()->clear();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($user)->getJson('/api/events')->assertOk();

        $queries = count(DB::getQueryLog());

        DB::disableQueryLog();

        return $queries;
    };

    expect($count(3))->toBe($count(30));
});

// Section 9 says this query is served by events (account_id, event_date).
// Asserted rather than assumed, and by the index's name rather than the
// plan's shape, because that output changes between Postgres versions and a
// brittle test here would be worse than none.
//
// The range is a single month, which is the case the index exists for and the
// one a windowed call makes. The app's own first call, from today with no
// upper bound, asks for most of the table and Postgres will rightly scan it
// sequentially however many indexes are available; asserting an index scan
// there would be asserting something false.
it('uses the account and date index for a windowed query', function () {
    $user = bookingsOwner();
    currentAccount()->set($user->accounts()->first());

    // A plan is only meaningful against rows: Postgres picks a sequential scan
    // on a tiny table whatever the indexes say.
    $booking = Booking::factory()->confirmed()->create();

    $rows = [];
    for ($i = 0; $i < 2000; $i++) {
        $rows[] = [
            'account_id' => currentAccount()->id(),
            'booking_id' => $booking->id,
            'type' => 'trial',
            'event_date' => today()->addDays($i % 1000)->toDateString(),
            'timezone' => 'Europe/London',
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
    DB::table('events')->insert($rows);
    DB::statement('analyze events');

    $from = today()->addDays(400)->toDateString();
    $to = today()->addDays(430)->toDateString();

    $plan = collect(DB::select(
        'explain select * from events where account_id = ? and event_date >= ? and event_date <= ? order by event_date',
        [currentAccount()->id(), $from, $to],
    ))->pluck('QUERY PLAN')->implode("\n");

    currentAccount()->clear();

    expect($plan)->toContain('events_account_id_event_date_index');
});
