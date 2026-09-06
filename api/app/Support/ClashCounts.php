<?php

namespace App\Support;

/**
 * What is already on a date, counted by how strongly it is held.
 *
 * Business logic 5.2: when an event is created or moved onto a date that
 * already carries a mark, the artist sees what is there and confirms past it.
 * Never a block. Two weddings in a day is normal, and a Saturday with four
 * enquiries on it is exactly the situation the warning exists to surface
 * rather than prevent. The enquiries list is the second place that fact
 * belongs.
 *
 * **The three buckets are the calendar's own, taken from strengthByStage in
 * app/src/lib/dayMarks.ts and not from an argument about which stages are in
 * the past.** confirmed, completed and closed all carry a filled mark;
 * provisional carries a ring; possible and quoted carry the count badge; new,
 * in_conversation, lost and cancelled carry nothing. Reasoning from "completed
 * is behind us" would be nearly always true and enforced by nothing, and the
 * first booking marked completed with its date still ahead would have the list
 * and the calendar describing the same Saturday differently. That is the exact
 * failure these counts exist to prevent.
 *
 * Counts are of bookings, not events: a booking with a trial and a main day on
 * one date is one thing on that date, not two.
 */
final class ClashCounts
{
    public function __construct(
        public readonly int $confirmed,
        public readonly int $provisional,
        public readonly int $others,
    ) {}

    /**
     * Nothing else is on the date. The payload sends null rather than three
     * zeroes, so a row with a free date and a row with no date at all read the
     * same way to a client that only has to ask "is there a clash".
     */
    public function isEmpty(): bool
    {
        return $this->confirmed === 0 && $this->provisional === 0 && $this->others === 0;
    }
}
