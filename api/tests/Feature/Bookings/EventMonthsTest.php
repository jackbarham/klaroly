<?php

use App\Enums\BookingStage;
use App\Models\Event;

// The month jump sheet's dots, and the bounds of its year strip. Presence,
// not counts, for every month the account holds an event in, for all time.

it('returns every month holding an event, sorted, oldest first', function () {
    $user = bookingsOwner();
    currentAccount()->set($user->accounts()->first());

    // Deliberately out of order, so a passing test cannot be one that happened
    // to insert them sorted.
    eventOn('2027-03-14');
    eventOn('2026-09-26');
    eventOn('2026-12-05');

    currentAccount()->clear();

    $this->actingAs($user)
        ->getJson('/api/events/months')
        ->assertOk()
        ->assertExactJson(['data' => ['2026-09', '2026-12', '2027-03']]);
});

it('lists a month once however many events it holds', function () {
    $user = bookingsOwner();
    currentAccount()->set($user->accounts()->first());

    eventOn('2026-09-05');
    eventOn('2026-09-12');
    eventOn('2026-09-26');

    currentAccount()->clear();

    // Presence and not counts: a count per month would serve a year view that
    // does not exist, and this is what says so.
    $this->actingAs($user)
        ->getJson('/api/events/months')
        ->assertOk()
        ->assertExactJson(['data' => ['2026-09']]);
});

it('reaches back before today, unlike the windowed endpoint', function () {
    $user = bookingsOwner();
    currentAccount()->set($user->accounts()->first());

    eventOn(today()->subYears(2)->toDateString());
    eventOn(today()->addDay()->toDateString());

    currentAccount()->clear();

    $this->actingAs($user)
        ->getJson('/api/events/months')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('counts a month whose only event belongs to an enquiry', function () {
    $user = bookingsOwner();
    currentAccount()->set($user->accounts()->first());

    // A soft hold is still work on the calendar (business logic 5.2), so the
    // month it falls in has to carry a dot like any other.
    eventOn('2027-06-12', ['stage' => BookingStage::Possible]);

    currentAccount()->clear();

    $this->actingAs($user)
        ->getJson('/api/events/months')
        ->assertOk()
        ->assertExactJson(['data' => ['2027-06']]);
});

it('leaves out a month with nothing in it', function () {
    $user = bookingsOwner();
    currentAccount()->set($user->accounts()->first());

    eventOn('2026-09-26');
    eventOn('2026-11-14');

    currentAccount()->clear();

    $months = $this->actingAs($user)->getJson('/api/events/months')->assertOk()->json('data');

    expect($months)->toBe(['2026-09', '2026-11'])
        ->and($months)->not->toContain('2026-10');
});

it('is empty for an account with no events at all', function () {
    $user = bookingsOwner();

    $this->actingAs($user)
        ->getJson('/api/events/months')
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

// This is the assertion that matters most on this endpoint. A distinct over a
// to_char reads like a query-builder job, and written as DB::table('events')
// it bypasses the account global scope and returns every account's months
// while looking perfectly correct in a development database with one account
// in it. Asserted separately from the windowed endpoint's tenancy test for
// exactly that reason.
it('never shows a month that belongs to another account', function () {
    $mine = bookingsOwner();
    $theirs = bookingsOwner();

    currentAccount()->set($theirs->accounts()->first());
    eventOn('2028-04-15');

    currentAccount()->set($mine->accounts()->first());
    eventOn('2026-09-26');

    currentAccount()->clear();

    $this->actingAs($mine)
        ->getJson('/api/events/months')
        ->assertOk()
        ->assertExactJson(['data' => ['2026-09']]);
});

it('refuses an unauthenticated caller', function () {
    $this->getJson('/api/events/months')->assertUnauthorized();
    $this->getJson('/api/events')->assertUnauthorized();
});
