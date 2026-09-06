<?php

namespace App\Services;

use App\Enums\BookingStage;
use App\Models\Event;
use App\Support\ClashCounts;

/**
 * What is already on each of a set of dates, in one query.
 *
 * Business logic 5.2 is the reason the enquiries list carries this at all: the
 * artist ringing round a contested Saturday needs to know the Saturday is
 * contested, and the payload already knows every date in the account. The
 * counts describe what is ALREADY on the date, which is why a row whose own
 * stage holds no calendar mark still gets them: an enquiry at in_conversation
 * carries nothing on the calendar and still reports the confirmed booking and
 * the two possible enquiries sitting on its Saturday (decision
 * 2026-09-06.1804). That is a deliberate departure from the calendar's rule
 * and not an oversight.
 *
 * **The buckets are the calendar's, from strengthByStage in
 * app/src/lib/dayMarks.ts.** See App\Support\ClashCounts for why they are
 * taken from that table rather than derived again here.
 *
 * **One query for every date, then match in memory.** Counting per row is the
 * obvious N+1 and it is worse than the usual kind, because it grows with the
 * number of distinct dates rather than with the number of rows, so it survives
 * every test written against a handful of enquiries sharing a Saturday.
 * EnquiryIndexTest holds the query count flat against both.
 *
 * The query is built from Event::query(), so the account global scope comes
 * with it. A join does not carry the joined model's scopes, which is why the
 * soft-delete check on bookings is written out: without it a deleted booking
 * would still clash. Nothing here writes where('account_id', ...) by hand and
 * nothing reaches for DB::table(): this reads like a query-builder job, and
 * written that way it counts every account's bookings while looking perfectly
 * correct in a development database with one account in it.
 */
class EnquiryClashes
{
    /**
     * The stages that hold a date, and how strongly, per strengthByStage.
     *
     * new, in_conversation, lost and cancelled are absent because they hold
     * nothing: the first two are an enquiry before the soft hold begins
     * (business logic 5.1 puts the hold at Possible), and the last two have
     * released the date.
     *
     * @var array<string, array<int, BookingStage>>
     */
    private const HOLDING_STAGES = [
        // Filled on the calendar: the work is on, or was worked.
        'confirmed' => [BookingStage::Confirmed, BookingStage::Completed, BookingStage::Closed],
        // A ring: pencilled in.
        'provisional' => [BookingStage::Provisional],
        // The count badge: the soft hold from business logic 5.1.
        'others' => [BookingStage::Possible, BookingStage::Quoted],
    ];

    /**
     * What sits on each of the given dates, keyed 'YYYY-MM-DD'.
     *
     * Every date asked about gets an entry, including the ones with nothing on
     * them, so a caller reads the map rather than testing whether a key
     * exists.
     *
     * @param  array<int, string>  $dates  'YYYY-MM-DD'
     * @return array<string, ClashCounts>
     */
    public function forDates(array $dates): array
    {
        $dates = array_values(array_unique($dates));

        if ($dates === []) {
            return [];
        }

        $held = $this->holdingBookings($dates);

        $counts = [];

        foreach ($dates as $date) {
            $onThisDate = $held[$date] ?? [];

            $counts[$date] = new ClashCounts(
                confirmed: count($onThisDate['confirmed'] ?? []),
                provisional: count($onThisDate['provisional'] ?? []),
                others: count($onThisDate['others'] ?? []),
            );
        }

        return $counts;
    }

    /**
     * The counts on one date as they look to one enquiry: everything above,
     * less that enquiry's own booking.
     *
     * A booking never clashes with itself, and it would: a possible enquiry
     * sitting alone on a date would otherwise report one other wanting it.
     *
     * @param  array<string, ClashCounts>  $counts  from forDates()
     */
    public function forRow(array $counts, string $date, BookingStage $stage): ClashCounts
    {
        $found = $counts[$date] ?? new ClashCounts(0, 0, 0);

        return new ClashCounts(
            confirmed: $found->confirmed - $this->ownShare($stage, 'confirmed'),
            provisional: $found->provisional - $this->ownShare($stage, 'provisional'),
            others: $found->others - $this->ownShare($stage, 'others'),
        );
    }

    /**
     * One for the bucket this booking's own stage counts towards, nought for
     * the other two.
     */
    private function ownShare(BookingStage $stage, string $bucket): int
    {
        return in_array($stage, self::HOLDING_STAGES[$bucket], true) ? 1 : 0;
    }

    /**
     * The ids of the bookings holding each date, by bucket. One query.
     *
     * Distinct booking ids rather than a count of events, because a booking
     * with a trial and a main day on the same date is one thing on that date.
     *
     * @param  array<int, string>  $dates
     * @return array<string, array<string, array<int, int>>>
     */
    private function holdingBookings(array $dates): array
    {
        $stages = array_map(
            fn (BookingStage $stage) => $stage->value,
            array_merge(...array_values(self::HOLDING_STAGES)),
        );

        $rows = Event::query()
            ->select('events.event_date', 'events.booking_id', 'bookings.stage')
            ->join('bookings', 'bookings.id', '=', 'events.booking_id')
            ->whereNull('bookings.deleted_at')
            ->whereIn('events.event_date', $dates)
            ->whereIn('bookings.stage', $stages)
            ->get();

        $held = [];

        foreach ($rows as $row) {
            $date = $row->event_date->format('Y-m-d');
            $bucket = $this->bucketFor(BookingStage::from($row->stage));

            // Keyed by booking id so a booking with two events on one date is
            // counted once.
            $held[$date][$bucket][$row->booking_id] = $row->booking_id;
        }

        return $held;
    }

    private function bucketFor(BookingStage $stage): string
    {
        foreach (self::HOLDING_STAGES as $bucket => $stages) {
            if (in_array($stage, $stages, true)) {
                return $bucket;
            }
        }

        // Unreachable: the query only asks for the stages in that table.
        throw new \LogicException(sprintf('Stage %s holds no date.', $stage->value));
    }
}
