<?php

use App\Enums\BookingStage;
use App\Enums\EventType;
use App\Enums\FeatureKey;
use App\Enums\LocationType;
use App\Models\Booking;
use App\Models\Event;
use App\Models\PartyMember;

// Business logic 18.2: the "what am I doing on Saturday" answer.

it('returns events from today forward, soonest first, across confirmed and provisional', function () {
    $user = bookingsOwner();
    currentAccount()->set($user->accounts()->first());

    $confirmed = Booking::factory()->confirmed()->create();
    $provisional = Booking::factory()->provisional()->create();

    // Created out of order, so a payload in insertion order would fail.
    Event::factory()->create(['booking_id' => $confirmed->id, 'event_date' => today()->addDays(20)]);
    Event::factory()->create(['booking_id' => $provisional->id, 'event_date' => today()->addDays(6)]);
    Event::factory()->create(['booking_id' => $confirmed->id, 'event_date' => today()->addDays(11), 'type' => EventType::Trial]);

    currentAccount()->clear();

    $response = $this->actingAs($user)->getJson('/api/home')->assertOk();

    expect(array_column($response->json('data.upcoming'), 'date'))->toBe([
        today()->addDays(6)->toDateString(),
        today()->addDays(11)->toDateString(),
        today()->addDays(20)->toDateString(),
    ]);

    expect(array_column($response->json('data.upcoming'), 'stage'))
        ->toBe(['provisional', 'confirmed', 'confirmed']);
});

/*
 * The unit is an event and not a booking, the same as GET /api/events and for
 * the same reason: a trial is a morning out of the artist's diary exactly as
 * the wedding is, so one booking with both is two rows here.
 */
it('sends a trial and a wedding day of one booking as two entries', function () {
    $user = bookingsOwner();
    currentAccount()->set($user->accounts()->first());

    $booking = Booking::factory()->confirmed()->create();
    Event::factory()->create(['booking_id' => $booking->id, 'event_date' => today()->addDays(30)]);
    Event::factory()->create([
        'booking_id' => $booking->id,
        'event_date' => today()->addDays(9),
        'type' => EventType::Trial,
    ]);

    currentAccount()->clear();

    $response = $this->actingAs($user)->getJson('/api/home')->assertOk();

    expect($response->json('data.upcoming'))->toHaveCount(2)
        ->and(array_column($response->json('data.upcoming'), 'type'))->toBe(['trial', 'main'])
        // Both carry the same booking, because four of the fields on a row are
        // per-booking rather than per-event.
        ->and(array_unique(array_column($response->json('data.upcoming'), 'booking_id')))
        ->toBe([$booking->id]);
});

it('leaves out yesterday and keeps today', function () {
    $user = bookingsOwner();
    currentAccount()->set($user->accounts()->first());

    $booking = Booking::factory()->confirmed()->create();
    Event::factory()->create(['booking_id' => $booking->id, 'event_date' => today()->subDay()]);
    Event::factory()->create(['booking_id' => $booking->id, 'event_date' => today(), 'type' => EventType::Trial]);

    currentAccount()->clear();

    $this->actingAs($user)->getJson('/api/home')->assertOk()
        ->assertJsonCount(1, 'data.upcoming')
        // A wedding this morning is the single most useful row this block can
        // carry, so "from today" is inclusive.
        ->assertJsonPath('data.upcoming.0.date', today()->toDateString());
});

it('leaves out an enquiry, a lost booking and a cancelled one', function () {
    $user = bookingsOwner();
    currentAccount()->set($user->accounts()->first());

    foreach ([BookingStage::Quoted, BookingStage::Lost, BookingStage::Cancelled] as $stage) {
        $booking = Booking::factory()->create(['stage' => $stage]);
        Event::factory()->create(['booking_id' => $booking->id, 'event_date' => today()->addDays(2)]);
    }

    // The presence half, so this cannot pass on a block that returns nothing.
    $confirmed = Booking::factory()->confirmed()->create();
    Event::factory()->create(['booking_id' => $confirmed->id, 'event_date' => today()->addDays(3)]);

    currentAccount()->clear();

    $this->actingAs($user)->getJson('/api/home')->assertOk()
        ->assertJsonCount(1, 'data.upcoming')
        ->assertJsonPath('data.upcoming.0.booking_id', $confirmed->id);
});

it('sends no more than the configured number', function () {
    $user = bookingsOwner();
    currentAccount()->set($user->accounts()->first());

    $booking = Booking::factory()->confirmed()->create();

    for ($i = 1; $i <= 10; $i++) {
        Event::factory()->create([
            'booking_id' => $booking->id,
            'event_date' => today()->addDays($i),
            'type' => EventType::Other,
        ]);
    }

    currentAccount()->clear();

    $this->actingAs($user)->getJson('/api/home')->assertOk()
        ->assertJsonCount(config('bookings.home_upcoming'), 'data.upcoming');
});

/*
 * Decision 228: location_type is nullable, which is four render cases and not
 * three. The venue columns cannot tell "nobody has said" from "at her own
 * place, whose address lives in settings", because both are a null venue_name
 * and a null city.
 */
it('sends the location type as it is, including null', function () {
    $user = bookingsOwner();
    currentAccount()->set($user->accounts()->first());

    $booking = Booking::factory()->confirmed()->create();

    Event::factory()->create([
        'booking_id' => $booking->id,
        'event_date' => today()->addDay(),
        'type' => EventType::Trial,
        'location_type' => LocationType::Base,
        'venue_name' => null,
        'city' => null,
    ]);
    Event::factory()->create([
        'booking_id' => $booking->id,
        'event_date' => today()->addDays(2),
        'location_type' => null,
        'venue_name' => null,
        'city' => null,
    ]);

    currentAccount()->clear();

    $this->actingAs($user)->getJson('/api/home')->assertOk()
        // At her own place: not a wedding with a missing venue.
        ->assertJsonPath('data.upcoming.0.location_type', 'base')
        // Nobody has said yet, which is the fourth case.
        ->assertJsonPath('data.upcoming.1.location_type', null);
});

/*
 * The venue and the town are two fields rather than one line, which is what the
 * prototype asked for: "The Old Corn Exchange, Saffron Walden" truncates at
 * 375px, so the screen drops the town and keeps the venue.
 */
it('sends the venue and the town separately', function () {
    $user = bookingsOwner();
    currentAccount()->set($user->accounts()->first());

    $booking = Booking::factory()->confirmed()->create();
    Event::factory()->create([
        'booking_id' => $booking->id,
        'event_date' => today()->addDays(4),
        'location_type' => LocationType::Venue,
        'venue_name' => 'The Old Corn Exchange',
        'city' => 'Saffron Walden',
    ]);

    currentAccount()->clear();

    $this->actingAs($user)->getJson('/api/home')->assertOk()
        ->assertJsonPath('data.upcoming.0.venue_name', 'The Old Corn Exchange')
        ->assertJsonPath('data.upcoming.0.city', 'Saffron Walden');
});

/*
 * The party as a number, never as words: the screen writes "Bride and 4" in its
 * own locale file. Null at nought rather than a nought, because a party of
 * nobody is not something anybody books, so the figure could only mean "the
 * party sheet is empty", which is "not known yet" wearing a number.
 */
it('sends the party as a count, and null when nobody is on it', function () {
    $user = bookingsOwner();
    currentAccount()->set($user->accounts()->first());

    $withParty = Booking::factory()->confirmed()->create();
    Event::factory()->create(['booking_id' => $withParty->id, 'event_date' => today()->addDay()]);
    PartyMember::factory()->count(5)->create(['booking_id' => $withParty->id]);

    $without = Booking::factory()->confirmed()->create();
    Event::factory()->create(['booking_id' => $without->id, 'event_date' => today()->addDays(2)]);

    currentAccount()->clear();

    $this->actingAs($user)->getJson('/api/home')->assertOk()
        ->assertJsonPath('data.upcoming.0.party_size', 5)
        ->assertJsonPath('data.upcoming.1.party_size', null);
});

it('sends the call time as HH:mm, and null when none is agreed', function () {
    $user = bookingsOwner();
    currentAccount()->set($user->accounts()->first());

    $booking = Booking::factory()->confirmed()->create();
    Event::factory()->create([
        'booking_id' => $booking->id,
        'event_date' => today()->addDay(),
        'start_time' => '06:30:00',
    ]);
    Event::factory()->create([
        'booking_id' => $booking->id,
        'event_date' => today()->addDays(2),
        'type' => EventType::Trial,
        'start_time' => null,
    ]);

    currentAccount()->clear();

    $this->actingAs($user)->getJson('/api/home')->assertOk()
        // 'HH:mm', not the column's 'HH:mm:ss'. And sent without a judgement
        // about whether it is early: an early start is a fact about a Saturday
        // rather than a fault.
        ->assertJsonPath('data.upcoming.0.start_time', '06:30')
        ->assertJsonPath('data.upcoming.1.start_time', null);
});

describe('the travel estimate', function () {
    it('sends seconds and metres when the artist uses travel estimates', function () {
        $user = bookingsOwner([FeatureKey::TravelEstimates->value => true]);
        currentAccount()->set($user->accounts()->first());

        $booking = Booking::factory()->confirmed()->create();
        Event::factory()->create([
            'booking_id' => $booking->id,
            'event_date' => today()->addDay(),
            'travel_duration_s' => 2520,
            'travel_distance_m' => 41400,
        ]);

        currentAccount()->clear();

        $this->actingAs($user)->getJson('/api/home')->assertOk()
            // Raw units, formatted at the edge like every other figure here.
            // "42 min" is the app's wording and not the API's.
            ->assertJsonPath('data.upcoming.0.travel_duration_s', 2520)
            ->assertJsonPath('data.upcoming.0.travel_distance_m', 41400);
    });

    it('sends null when the artist has travel estimates switched off', function () {
        $user = bookingsOwner([FeatureKey::TravelEstimates->value => false]);
        currentAccount()->set($user->accounts()->first());

        $booking = Booking::factory()->confirmed()->create();
        Event::factory()->create([
            'booking_id' => $booking->id,
            'event_date' => today()->addDay(),
            'travel_duration_s' => 2520,
            'travel_distance_m' => 41400,
        ]);

        currentAccount()->clear();

        // The columns hold a figure and the payload still says nothing: a
        // figure from a feature she has turned off is one she has said she does
        // not want.
        $this->actingAs($user)->getJson('/api/home')->assertOk()
            ->assertJsonPath('data.upcoming.0.travel_duration_s', null)
            ->assertJsonPath('data.upcoming.0.travel_distance_m', null);
    });
});
