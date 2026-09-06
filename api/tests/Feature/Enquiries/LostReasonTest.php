<?php

use App\Enums\EndingSide;
use App\Enums\LostReason;
use App\Models\Booking;

/**
 * How an enquiry ended.
 *
 * **A reason with a side, not a stage** (decision 2026-09-06.1512). The client
 * goes elsewhere, or the artist turns the work down, and the two behave
 * identically: both release the date, both archive the record, and the only
 * thing that differs is who decided. A tenth stage would have bought the same
 * label and charged for it in strengthByStage, in WaitingOnResolver, in both
 * list filters, in the stage check constraint and in every future test of
 * whether a record is still live.
 */
it('round-trips through the cast as the enum, not a string', function () {
    actingForAccount();

    $booking = Booking::factory()->lost(LostReason::NotRightFit)->create();

    expect($booking->fresh()->lost_reason)->toBe(LostReason::NotRightFit);
});

it('is null on a booking that has not ended', function () {
    actingForAccount();

    expect(Booking::factory()->quoted()->create()->fresh()->lost_reason)->toBeNull();
});

// One from each group, because the whole point of the enum is that the nine
// values fall into two sides and the side is the fact being recorded.
it('knows which side ended it', function () {
    expect(LostReason::NoReply->side())->toBe(EndingSide::Client)
        ->and(LostReason::AlreadyBooked->side())->toBe(EndingSide::Artist);
});

/**
 * The guard against a tenth value arriving without a side.
 *
 * side() is a match with no default, so an unhandled case throws rather than
 * reporting the wrong side, and this is what makes that fail here rather than
 * on a screen. It also pins the split, because a value quietly moved from one
 * side to the other would change the turned-away figure without changing
 * anything visible.
 */
it('splits the nine values five to the client and four to the artist', function () {
    $sides = array_map(fn (LostReason $reason) => $reason->side(), LostReason::cases());

    expect(LostReason::cases())->toHaveCount(9)
        ->and(count(array_filter($sides, fn (EndingSide $side) => $side === EndingSide::Client)))->toBe(5)
        ->and(count(array_filter($sides, fn (EndingSide $side) => $side === EndingSide::Artist)))->toBe(4);
});

/**
 * Silence is the most common ending of all, and without a value for it the
 * artist either leaves the enquiry in the list for ever or files it under
 * something untrue. Named here rather than only in the enum's docblock,
 * because a value nobody has a test for is a value somebody tidies away.
 */
it('has a value for never having heard back, on the client\'s side', function () {
    expect(LostReason::tryFrom('no_reply'))->toBe(LostReason::NoReply)
        ->and(LostReason::NoReply->side())->toBe(EndingSide::Client);
});

/**
 * The column has no check constraint yet: the enum holds the line at the
 * application boundary and the constraint goes in with the schema rewrite
 * rather than as an ALTER migration of its own. This asserts the SQL that
 * migration will use is generated from this enum rather than typed out a
 * second time, which is the rule for every other enum-backed column.
 */
it('can generate its own check constraint for the schema rewrite', function () {
    $sql = LostReason::checkConstraintSql('bookings', 'lost_reason');

    expect($sql)->toContain('bookings_lost_reason_check')
        ->and($sql)->toContain("'no_reply'")
        ->and($sql)->toContain("'artist_other'");
});
