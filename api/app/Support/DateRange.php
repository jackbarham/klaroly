<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * A pair of local calendar days, both ends included.
 *
 * Days rather than instants, because every period on the home screen's money
 * block is a run of days in the artist's own calendar: "this month" is a set of
 * dates, and turning it into a moment is what puts a payment recorded on the
 * evening of the 30th into the following month for the eight months the clocks
 * are forward.
 */
final class DateRange
{
    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
    ) {}

    /**
     * Whether a day falls inside the range.
     *
     * Compared as 'Y-m-d' strings rather than with between(), because the two
     * ends and the day being tested can carry different times of day and a
     * comparison that reads the time would exclude the last day of the range.
     */
    public function holds(CarbonImmutable $day): bool
    {
        $value = $day->format('Y-m-d');

        return $value >= $this->from->format('Y-m-d')
            && $value <= $this->to->format('Y-m-d');
    }
}
