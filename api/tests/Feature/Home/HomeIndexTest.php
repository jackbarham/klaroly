<?php

use App\Models\Account;
use App\Models\Booking;
use App\Models\Event;

// GET /api/home. Business logic section 18's three blocks in one payload.

it('returns the three blocks and the meta block, with every key the app expects', function () {
    $user = bookingsOwner();
    currentAccount()->set($user->accounts()->first());

    $booking = Booking::factory()->confirmed()->create();
    Event::factory()->create(['booking_id' => $booking->id, 'event_date' => today()->addDays(5)]);

    currentAccount()->clear();

    $response = $this->actingAs($user)->getJson('/api/home')->assertOk();

    expect(array_keys($response->json('data')))->toBe(HOME_KEYS)
        ->and(array_keys($response->json('meta')))->toBe(HOME_META_KEYS)
        ->and(array_keys($response->json('data.money')))->toBe(HOME_MONEY_KEYS)
        ->and(array_keys($response->json('data.upcoming.0')))->toBe(HOME_UPCOMING_KEYS)
        ->and(array_keys($response->json('data.money.outstanding')))->toBe(HOME_OUTSTANDING_KEYS)
        ->and(array_keys($response->json('data.money.periods')))->toBe(HOME_PERIODS)
        ->and(array_keys($response->json('data.money.periods.this_month')))->toBe(HOME_PERIOD_KEYS);
});

/*
 * The screen an artist meets before they have done anything, which the
 * prototype calls the one most likely to lose somebody. Every block is empty
 * and the app draws its first-run state from that, so what it must not get is
 * a 404, a null where an array belongs, or an absent money object.
 */
it('returns empty arrays and a money object on an account with nothing at all', function () {
    $user = bookingsOwner();

    $response = $this->actingAs($user)->getJson('/api/home')->assertOk();

    expect($response->json('data.attention'))->toBe([])
        ->and($response->json('data.upcoming'))->toBe([])
        ->and($response->json('data.money'))->toBeArray()
        ->and($response->json('data.money.owed_minor'))->toBe(0)
        ->and($response->json('data.money.booked_ahead_minor'))->toBe(0)
        ->and($response->json('data.money.periods.twelve_months.value_minor'))->toBe(0)
        // Nought rather than null, so the screen draws "0 weddings, £0 average"
        // rather than carrying a second empty state for a quiet month.
        ->and($response->json('data.money.periods.twelve_months.average_value_minor'))->toBe(0)
        ->and($response->json('meta.attention.total'))->toBe(0);
});

it('reports the account\'s own day and zone rather than the application\'s', function () {
    $account = Account::factory()->withSettings()->create(['timezone' => 'Pacific/Auckland']);
    $user = createOwner([], $account);

    $this->actingAs($user)
        ->getJson('/api/home')
        ->assertOk()
        ->assertJsonPath('meta.timezone', 'Pacific/Auckland')
        // Not the UTC day. APP_TIMEZONE is UTC and Auckland is most of a day
        // ahead of it, so an artist there is regularly on a different date from
        // the application, and every day count on the screen is worked out
        // against this one.
        ->assertJsonPath('meta.today', now('Pacific/Auckland')->toDateString());
});

it('never shows another account\'s work', function () {
    $mine = bookingsOwner();
    $theirs = bookingsOwner();

    currentAccount()->set($theirs->accounts()->first());
    $booking = Booking::factory()->confirmed()->create();
    Event::factory()->create(['booking_id' => $booking->id, 'event_date' => today()->addDays(3)]);

    currentAccount()->set($mine->accounts()->first());
    $mineBooking = Booking::factory()->confirmed()->create();
    Event::factory()->create(['booking_id' => $mineBooking->id, 'event_date' => today()->addDays(4)]);

    currentAccount()->clear();

    // The presence half is what makes this mean anything: an endpoint that
    // returned nothing at all would pass the absence assertion on its own.
    $this->actingAs($mine)
        ->getJson('/api/home')
        ->assertOk()
        ->assertJsonCount(1, 'data.upcoming')
        ->assertJsonPath('data.upcoming.0.booking_id', $mineBooking->id);
});

it('refuses a caller who is not signed in', function () {
    $this->getJson('/api/home')->assertUnauthorized();
});
