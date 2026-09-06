<?php

use App\Enums\AgreementStatus;
use App\Enums\BookingStage;
use App\Enums\FeatureKey;
use App\Enums\WaitingOn;
use App\Models\Agreement;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Quote;
use App\Services\SoftHold;
use App\Services\WaitingOnResolver;
use Carbon\CarbonImmutable;

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

/*
 * **The last day of a hold is still a hold**, and this is the assertion that
 * could not have existed before there were real values to hold it against.
 *
 * The column is a date and the cast is immutable_date, so the old isPast() was
 * true from one second after midnight on the expiry day itself: a fourteen-day
 * hold was dead on day fourteen rather than after it. Every wording around the
 * feature is inclusive, and "held until 4 October" covers the fourth.
 *
 * No existing test could have caught it, because every fixture set the hold a
 * day or more either side of today and none of them touched the boundary. That
 * is decision 197's lesson again: a test that never touches the edge is
 * documentation rather than a guard.
 */
it('leaves a provisional booking alone on the last day of its hold', function () {
    accountWithFeatures();

    $booking = Booking::factory()->provisional()->create([
        'hold_expires_at' => today(),
    ]);

    expect(waitingOnFor($booking))->toBeNull();

    // The presence half, one day further on, which is what makes the absence
    // above about the boundary rather than about holds in general.
    $booking->forceFill(['hold_expires_at' => today()->subDay()])->save();

    expect(waitingOnFor($booking))->toBe(WaitingOn::ArtistNotHeld);
});

/*
 * And judged in the artist's own day rather than the application's. This was
 * the last comparison of a stored date against the present still asking UTC,
 * after Invoice::isOverdue(), OutstandingBalances and BusinessPeriods all moved
 * off it.
 */
it('judges a hold in the account\'s own day', function () {
    // **Frozen at an instant where the two days genuinely differ**, or this
    // test proves nothing: for most of the day UTC and Auckland agree on the
    // date and every implementation gives the same answer. Ten in the evening
    // UTC is eleven the next morning in Auckland.
    $this->travelTo(CarbonImmutable::parse('2027-03-10 22:00:00', 'UTC'));

    $account = accountWithFeatures();
    $account->update(['timezone' => 'Pacific/Auckland']);

    // The tenth is the application's today and the artist's yesterday, which is
    // the one date the two disagree about. In her day the hold ran out
    // yesterday; in the application's it runs out at the end of today.
    $booking = Booking::factory()->provisional()->create([
        'hold_expires_at' => '2027-03-10',
    ]);

    expect(waitingOnFor($booking->fresh()))->toBe(WaitingOn::ArtistNotHeld);
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

/*
 * Snoozing wins (decision 2026-09-06.1436). Snoozing an invoice means "I know,
 * stop telling me", and a waiting-on value that ignores it makes the snooze
 * useless: the artist clears the reminder and the same row is on the home
 * screen the next morning.
 *
 * These three are the behaviour change, and they are here rather than folded
 * into the home endpoint's own tests because the change lands on
 * GET /api/events and GET /api/enquiries as well.
 */
describe('a snoozed invoice', function () {
    it('says nothing about an overdue balance while the snooze stands', function () {
        accountWithFeatures();

        $booking = Booking::factory()->confirmed()->create();

        $invoice = Invoice::factory()->issued()->snoozedUntil(today()->addWeek()->toDateString())->create([
            'booking_id' => $booking->id,
            'balance_due_on' => today()->subDays(3),
            'deposit_minor' => 0,
        ]);

        expect(waitingOnFor($booking))->toBeNull();

        // The presence half, and it is what makes the absence above mean
        // anything: the same booking and the same overdue balance with the
        // snooze lifted. Without it this test would pass just as happily
        // against a resolver that reported nothing for any invoice at all.
        $invoice->forceFill(['reminders_snoozed_until' => null])->save();

        expect(waitingOnFor($booking))->toBe(WaitingOn::ClientBalance);
    });

    it('reports the balance again once the snooze has run out', function () {
        accountWithFeatures();

        $booking = Booking::factory()->confirmed()->create();

        // Yesterday. The invoice knows the difference between a snooze that
        // still stands and one that has expired, and this is the boundary.
        Invoice::factory()->issued()->snoozedUntil(today()->subDay()->toDateString())->create([
            'booking_id' => $booking->id,
            'balance_due_on' => today()->subDays(3),
            'deposit_minor' => 0,
        ]);

        expect(waitingOnFor($booking))->toBe(WaitingOn::ClientBalance);
    });

    /*
     * deposit() deliberately did not gain the same check, so this pins what
     * that means rather than leaving it to be discovered. A snooze is about
     * chasing money; the deposit branch is about the date not being secured,
     * which is still true whether or not anybody is being chased. So the row
     * does not disappear, it changes what it is about.
     */
    it('still reports an unpaid deposit, because that is about the date and not the chasing', function () {
        accountWithFeatures();

        $booking = Booking::factory()->confirmed()->create();

        Invoice::factory()->issued()->snoozedUntil(today()->addWeek()->toDateString())->create([
            'booking_id' => $booking->id,
            'balance_due_on' => today()->subDays(3),
            'deposit_minor' => 11250,
        ]);

        expect(waitingOnFor($booking))->toBe(WaitingOn::ClientDeposit);
    });
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

/**
 * The widening, and the boundary it stopped at.
 *
 * enquiryCold() fired only at Possible, which was right while the home
 * screen's attention block was the only consumer and wrong for
 * GET /api/enquiries: a quote sent three weeks ago with no reply is the most
 * actionable row on that screen, and an in-conversation enquiry that has gone
 * silent is the second. Both are things the artist has not done, which is what
 * this axis is for.
 */
describe('the stages a cold enquiry can be at', function () {
    it('calls a quoted enquiry cold', function () {
        accountWithFeatures();

        $booking = Booking::factory()->quoted()->create([
            'last_touched_at' => now()->subDays(config('bookings.cold_enquiry_days') + 1),
        ]);

        expect(waitingOnFor($booking))->toBe(WaitingOn::ArtistEnquiryCold);
    });

    it('calls an in-conversation enquiry cold', function () {
        accountWithFeatures();

        $booking = Booking::factory()->inConversation()->create([
            'last_touched_at' => now()->subDays(config('bookings.cold_enquiry_days') + 1),
        ]);

        expect(waitingOnFor($booking))->toBe(WaitingOn::ArtistEnquiryCold);
    });

    /**
     * The other side of the widening, and the assertion that bounds it.
     *
     * Cold is about a live enquiry, so it stops at the boundary between the two
     * lists (decision 235). Without this, "widen it" and "remove the stage
     * check altogether" pass the same tests, and a provisional booking whose
     * hold is still good would be reported as gone quiet for the whole month
     * before the wedding.
     */
    it('says nothing about a provisional booking left alone for just as long', function () {
        accountWithFeatures();

        $booking = Booking::factory()->provisional()->create([
            'hold_expires_at' => today()->addMonth(),
            'last_touched_at' => now()->subDays(config('bookings.cold_enquiry_days') + 1),
        ]);

        expect(waitingOnFor($booking))->toBeNull();
    });

    // And the archive. A lost enquiry cannot go quiet: it has ended.
    it('says nothing about a lost enquiry', function () {
        accountWithFeatures();

        $booking = Booking::factory()->lost()->create([
            'last_touched_at' => now()->subDays(config('bookings.cold_enquiry_days') + 1),
        ]);

        expect(waitingOnFor($booking))->toBeNull();
    });
});

/**
 * An archived record waits on nobody, because nobody is going to act on it.
 *
 * The guard is at the top of WaitingOnResolver::for() rather than a filter
 * over its answer, because this is the question and a caller discarding an
 * answer it did not want would be a second opinion held somewhere else. It
 * arrived with PATCH /api/enquiries/{booking}, which is the first thing in the
 * app that creates lost rows and so the first thing that would have shipped
 * the noise.
 */
describe('an archived booking', function () {
    it('says nothing about a lost enquiry whose agreement was sent and never signed', function () {
        accountWithFeatures();

        $booking = Booking::factory()->lost()->create();

        Agreement::factory()->create([
            'booking_id' => $booking->id,
            'status' => AgreementStatus::Sent,
        ]);

        expect(waitingOnFor($booking))->toBeNull();
    });

    it('says nothing about a cancelled booking with an overdue balance', function () {
        accountWithFeatures();

        $booking = Booking::factory()->cancelled()->create();

        Invoice::factory()->issued()->create([
            'booking_id' => $booking->id,
            'balance_due_on' => today()->subDays(3),
            'deposit_minor' => 0,
        ]);

        expect(waitingOnFor($booking))->toBeNull();
    });

    /**
     * The assertion that bounds the guard, and without it "return null at the
     * top" and "return null always" pass the same two tests above. An
     * identical booking that is not archived still reports.
     */
    it('still reports on the same booking when it is not archived', function () {
        accountWithFeatures();

        $booking = Booking::factory()->confirmed()->create();

        Invoice::factory()->issued()->create([
            'booking_id' => $booking->id,
            'balance_due_on' => today()->subDays(3),
            'deposit_minor' => 0,
        ]);

        expect(waitingOnFor($booking))->toBe(WaitingOn::ClientBalance);
    });
});

/*
 * Decision 217's first line, and it had never been tested against a hold the
 * application itself wrote: losing a date beats being owed money.
 */
it('puts a lapsed hold above an overdue balance on a hold the app wrote itself', function () {
    $account = accountWithFeatures();

    $booking = Booking::factory()->create([
        'stage' => BookingStage::Possible,
        'hold_expires_at' => null,
    ]);

    // Written the way the stage route writes it, from a conversion three weeks
    // ago, rather than set to a date chosen to make the test pass.
    $booking->forceFill([
        'stage' => BookingStage::Provisional,
        'hold_expires_at' => app(SoftHold::class)->forTransition(
            from: BookingStage::Possible,
            to: BookingStage::Provisional,
            on: today()->subDays(21),
        ),
    ])->save();

    Invoice::factory()->issued()->create([
        'booking_id' => $booking->id,
        'balance_due_on' => today()->subDays(9),
        'deposit_minor' => 0,
    ]);

    expect($booking->hold_expires_at->toDateString())->toBe(today()->subDays(7)->toDateString())
        ->and(waitingOnFor($booking))->toBe(WaitingOn::ArtistNotHeld);
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
