<?php

namespace App\Support;

/**
 * One period's three figures on the home screen's money block: what it came to,
 * how many bookings that was, and the average of the two.
 *
 * **All three are computed on one basis, and the average is derived rather than
 * counted separately.** Business logic 18.3 asks for the count and the average
 * because they are what an artist wants when deciding whether to put prices up,
 * and a cash figure beside an accrual count would be two answers to different
 * questions sitting under one heading with nothing saying so. So when the basis
 * is payments all three are about payments, and when it is booking value all
 * three are about bookings. App\Support\MoneySummary carries which.
 *
 * The average is nought rather than null when the count is nought, because the
 * screen draws "0 weddings, £0 average" on a quiet month and a null there would
 * be a second empty state for no reason.
 */
final class PeriodTotals
{
    public function __construct(
        public readonly DateRange $range,
        public readonly int $valueMinor,
        public readonly int $bookingCount,
    ) {}

    public static function empty(DateRange $range): self
    {
        return new self($range, 0, 0);
    }

    /**
     * Integer division, so the average is a whole number of minor units. A
     * fraction of a penny is not a thing anybody is owed, and rounding it here
     * rather than at the edge keeps the figure a Money-shaped integer like
     * every other one in the payload (decision 77).
     */
    public function averageValueMinor(): int
    {
        return $this->bookingCount === 0
            ? 0
            : intdiv($this->valueMinor, $this->bookingCount);
    }
}
