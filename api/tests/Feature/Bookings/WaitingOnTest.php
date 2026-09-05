<?php

use App\Enums\AgreementStatus;
use App\Enums\FeatureKey;
use App\Enums\WaitingOn;
use App\Models\Agreement;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Quote;
use App\Services\WaitingOnResolver;

// Axis two of the booking lifecycle, business logic section 6.

/**
 * The relations the resolver reads, loaded the way the endpoint loads them,
 * so a test can never pass on a lazily loaded relation the real caller does
 * not have.
 */
function waitingOnFor(Booking $booking): ?WaitingOn
{
    $booking->load(['quotes', 'agreements', 'invoices.payments', 'account.settings']);

    return app(WaitingOnResolver::class)->for($booking);
}

it('says nothing is outstanding on a confirmed booking with nothing owing', function () {
    accountWithFeatures();

    $booking = Booking::factory()->confirmed()->create();

    expect(waitingOnFor($booking))->toBeNull();
});

it('reports a provisional booking whose hold has lapsed', function () {
    accountWithFeatures();

    $booking = Booking::factory()->provisional()->create([
        'hold_expires_at' => today()->subDay(),
    ]);

    expect(waitingOnFor($booking))->toBe(WaitingOn::ArtistNotHeld);
});

it('leaves a provisional booking alone while its hold still stands', function () {
    accountWithFeatures();

    $booking = Booking::factory()->provisional()->create([
        'hold_expires_at' => today()->addDays(10),
    ]);

    expect(waitingOnFor($booking))->toBeNull();
});

it('reports an overdue balance', function () {
    accountWithFeatures();

    $booking = Booking::factory()->confirmed()->create();

    Invoice::factory()->issued()->create([
        'booking_id' => $booking->id,
        'balance_due_on' => today()->subDays(3),
        'deposit_minor' => 0,
    ]);

    expect(waitingOnFor($booking))->toBe(WaitingOn::ClientBalance);
});

it('reports an unpaid deposit', function () {
    accountWithFeatures();

    $booking = Booking::factory()->confirmed()->create();

    Invoice::factory()->issued()->create([
        'booking_id' => $booking->id,
        'balance_due_on' => today()->addDays(30),
        'deposit_minor' => 11250,
    ]);

    expect(waitingOnFor($booking))->toBe(WaitingOn::ClientDeposit);
});

it('ignores a draft invoice, which carries no money until it is issued', function () {
    accountWithFeatures();

    $booking = Booking::factory()->confirmed()->create();

    Invoice::factory()->create([
        'booking_id' => $booking->id,
        'deposit_minor' => 11250,
    ]);

    expect(waitingOnFor($booking))->toBeNull();
});

it('reports an agreement that was sent and not signed', function () {
    accountWithFeatures();

    $booking = Booking::factory()->provisional()->create(['hold_expires_at' => null]);

    Agreement::factory()->create([
        'booking_id' => $booking->id,
        'status' => AgreementStatus::Sent,
    ]);

    expect(waitingOnFor($booking))->toBe(WaitingOn::ClientSignature);
});

it('stops waiting on a signature once a later version is signed', function () {
    accountWithFeatures();

    $booking = Booking::factory()->provisional()->create(['hold_expires_at' => null]);

    Agreement::factory()->create([
        'booking_id' => $booking->id,
        'version' => 1,
        'status' => AgreementStatus::Sent,
    ]);
    Agreement::factory()->signed()->create([
        'booking_id' => $booking->id,
        'version' => 2,
    ]);

    expect(waitingOnFor($booking))->toBeNull();
});

it('reports an enquiry at possible that has not been priced', function () {
    accountWithFeatures([FeatureKey::IntakeForms->value => false]);

    $booking = Booking::factory()->possible()->create([
        'last_touched_at' => now(),
    ]);

    expect(waitingOnFor($booking))->toBe(WaitingOn::ArtistPrice);
});

it('stops asking for a price once a quote exists', function () {
    accountWithFeatures([FeatureKey::IntakeForms->value => false]);

    $booking = Booking::factory()->possible()->create(['last_touched_at' => now()]);

    Quote::factory()->create(['booking_id' => $booking->id]);

    expect(waitingOnFor($booking))->toBeNull();
});

// The gate is whether the intake form exists, not whether the artist has
// switched it on. This is the case that made the difference matter: the demo
// account has intake forms on, and under the toggle an enquiry at Possible
// with no quote carried no pill at all until it went cold three weeks later.
it('still asks for a price when the intake toggle is on, because there is no form to review yet', function () {
    accountWithFeatures([FeatureKey::IntakeForms->value => true]);

    $booking = Booking::factory()->possible()->create(['last_touched_at' => now()]);

    expect(waitingOnFor($booking))->toBe(WaitingOn::ArtistPrice);
});

// And the other side of the same gate: once the form exists, "reviewed, no
// quote built" is the real trigger and this fallback stands down.
it('stops asking for a price once the intake form exists', function () {
    accountWithFeatures([FeatureKey::IntakeForms->value => true]);
    config(['bookings.intake_available' => true]);

    $booking = Booking::factory()->possible()->create(['last_touched_at' => now()]);

    expect(waitingOnFor($booking))->toBeNull();
});

it('reports an enquiry nobody has touched for longer than the cold period', function () {
    accountWithFeatures([FeatureKey::IntakeForms->value => false]);

    $booking = Booking::factory()->possible()->create([
        'last_touched_at' => now()->subDays(config('bookings.cold_enquiry_days') + 1),
    ]);

    expect(waitingOnFor($booking))->toBe(WaitingOn::ArtistEnquiryCold);
});

// The pair that would have hidden the precedence bug. Both describe an
// enquiry at Possible with no quote against it, so with price above cold the
// cold value could never be reported at all: a cold enquiry is at Possible by
// definition, and one with a quote would be at Quoted. Only the two together
// prove the order.
describe('the precedence of a cold enquiry over an unpriced one', function () {
    it('asks for a price when the enquiry was touched today', function () {
        accountWithFeatures([FeatureKey::IntakeForms->value => false]);

        $booking = Booking::factory()->possible()->create(['last_touched_at' => now()]);

        expect(waitingOnFor($booking))->toBe(WaitingOn::ArtistPrice);
    });

    it('calls the same enquiry cold once it has been sitting longer than the cold period', function () {
        accountWithFeatures([FeatureKey::IntakeForms->value => false]);

        $booking = Booking::factory()->possible()->create([
            'last_touched_at' => now()->subDays(config('bookings.cold_enquiry_days') + 1),
        ]);

        expect(waitingOnFor($booking))->toBe(WaitingOn::ArtistEnquiryCold);
    });
});

it('puts a lapsed hold above an overdue balance when both are true', function () {
    accountWithFeatures();

    $booking = Booking::factory()->provisional()->create([
        'hold_expires_at' => today()->subDay(),
    ]);

    Invoice::factory()->issued()->create([
        'booking_id' => $booking->id,
        'balance_due_on' => today()->subDays(3),
        'deposit_minor' => 11250,
    ]);

    // All three of not held, balance and deposit apply. Losing the date beats
    // being owed money.
    expect(waitingOnFor($booking))->toBe(WaitingOn::ArtistNotHeld);
});

it('puts an overdue balance above an unpaid deposit when both are true', function () {
    accountWithFeatures();

    $booking = Booking::factory()->confirmed()->create();

    Invoice::factory()->issued()->create([
        'booking_id' => $booking->id,
        'balance_due_on' => today()->subDays(3),
        'deposit_minor' => 11250,
    ]);

    expect(waitingOnFor($booking))->toBe(WaitingOn::ClientBalance);
});

// Suppression is part of the calculation, not a filter over the top of it.
it('never waits on money when invoicing is switched off', function () {
    accountWithFeatures([FeatureKey::Invoicing->value => false]);

    $booking = Booking::factory()->confirmed()->create();

    Invoice::factory()->issued()->create([
        'booking_id' => $booking->id,
        'balance_due_on' => today()->subDays(3),
        'deposit_minor' => 11250,
    ]);

    expect(waitingOnFor($booking))->toBeNull();
});

it('never waits on a signature when agreements are switched off', function () {
    accountWithFeatures([FeatureKey::Agreements->value => false]);

    $booking = Booking::factory()->provisional()->create(['hold_expires_at' => null]);

    Agreement::factory()->create([
        'booking_id' => $booking->id,
        'status' => AgreementStatus::Sent,
    ]);

    expect(waitingOnFor($booking))->toBeNull();
});

it('lets a booking switch invoicing off for itself', function () {
    accountWithFeatures([FeatureKey::Invoicing->value => true]);

    $booking = Booking::factory()->confirmed()->create([
        'feature_overrides' => [FeatureKey::Invoicing->value => false],
    ]);

    Invoice::factory()->issued()->create([
        'booking_id' => $booking->id,
        'balance_due_on' => today()->subDays(3),
        'deposit_minor' => 11250,
    ]);

    expect(waitingOnFor($booking))->toBeNull();
});

// The two the schema cannot answer yet. This asserts they are unreachable by
// design rather than by accident: both turn on intake_forms, which is schema
// section 7.4, designed and not migrated. When it lands, these two tests are
// what say the branches were waiting rather than forgotten.
it('cannot yet reach the two values that need the intake form', function () {
    accountWithFeatures([FeatureKey::IntakeForms->value => true]);

    // Every stage, with nothing else outstanding, so that if either value
    // could fire from the data that does exist, one of these would find it.
    foreach (Booking::ENQUIRY_STAGES as $stage) {
        $booking = Booking::factory()->stage($stage)->create(['last_touched_at' => now()]);

        expect(waitingOnFor($booking))->not->toBe(WaitingOn::ClientForm)
            ->and(waitingOnFor($booking))->not->toBe(WaitingOn::ArtistReview);
    }
});
