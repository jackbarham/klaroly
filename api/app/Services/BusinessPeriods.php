<?php

namespace App\Services;

use App\Models\Account;
use App\Support\DateRange;
use Carbon\CarbonImmutable;

/**
 * The four periods the home screen's money block offers, business logic 18.3:
 * this month, three months, twelve months and the business year.
 *
 * A calendar rather than a money service. It knows nothing about payments or
 * bookings; it answers "which days is the artist asking about", and the two
 * services that sum things take the answer.
 *
 * **Every period ends today, and that is what keeps the block coherent.** A
 * period figure looks backwards and the as-of-today figures look forwards, so a
 * wedding later this month belongs to booked ahead rather than to this month.
 * Ending "this month" at the month end instead would put a job that has not
 * happened yet into a figure sitting beside one that reports cash received, and
 * the two would answer different questions under one selector.
 *
 * Every boundary is a day in the artist's own timezone. APP_TIMEZONE is UTC, so
 * today() is a UTC day, and for the last hour of a British summer evening that
 * is already tomorrow: a payment recorded this evening would fall outside "this
 * month" on the 30th. The same reason App\Models\Invoice::isOverdue() takes a
 * day rather than assuming one.
 */
class BusinessPeriods
{
    public const THIS_MONTH = 'this_month';

    public const THREE_MONTHS = 'three_months';

    public const TWELVE_MONTHS = 'twelve_months';

    public const BUSINESS_YEAR = 'business_year';

    /**
     * All four, keyed by name.
     *
     * @return array<string, DateRange>
     */
    public function all(Account $account): array
    {
        $today = $this->today($account);

        return [
            self::THIS_MONTH => new DateRange($today->startOfMonth(), $today),
            // Inclusive windows, so the start is the day after the one three
            // months back: today plus the three months before it, not three
            // months and a day.
            self::THREE_MONTHS => new DateRange($today->subMonths(3)->addDay(), $today),
            self::TWELVE_MONTHS => new DateRange($today->subYear()->addDay(), $today),
            self::BUSINESS_YEAR => new DateRange($this->businessYearStart($account, $today), $today),
        ];
    }

    /**
     * The earliest day any of the four reaches back to.
     *
     * The one query behind the period figures asks for this and buckets what
     * comes back, rather than running a query per period. Which of the four is
     * earliest is not fixed: in April a business year starting on the 6th is
     * days old and twelve months wins, and in March it is nearly a year old and
     * still loses. So it is a min() rather than a named period.
     */
    public function earliest(Account $account): CarbonImmutable
    {
        $starts = array_map(fn (DateRange $range) => $range->from, $this->all($account));

        return array_reduce(
            $starts,
            fn (?CarbonImmutable $earliest, CarbonImmutable $start) => $earliest === null || $start->lessThan($earliest)
                ? $start
                : $earliest,
        );
    }

    /**
     * The start of the business year that today falls in.
     *
     * Sole traders are commonly on 6 April rather than 1 January, which is why
     * account_settings carries the month and day and why they default to 4 and
     * 6. A start later in the calendar year than today means the current
     * business year began in the previous one.
     */
    private function businessYearStart(Account $account, CarbonImmutable $today): CarbonImmutable
    {
        $settings = $account->settings;

        $month = (int) ($settings?->business_year_start_month ?? 4);
        $day = (int) ($settings?->business_year_start_day ?? 6);

        $start = $this->dayIn($today->year, $month, $day, $today->timezone->getName());

        return $start->greaterThan($today)
            ? $this->dayIn($today->year - 1, $month, $day, $today->timezone->getName())
            : $start;
    }

    /**
     * A day, with the day number clamped to the length of that month.
     *
     * **29 February is the whole reason this is a method.** Carbon overflows
     * rather than refusing, so create(2027, 2, 29) is quietly 1 March 2027, and
     * an artist whose business year starts on the 29th would have it start on a
     * different date in three years out of four with nothing reporting it. The
     * schema allows the pair to be any month and day, so the clamp belongs here
     * rather than in a validation rule that does not exist yet.
     */
    private function dayIn(int $year, int $month, int $day, string $timezone): CarbonImmutable
    {
        $firstOfMonth = CarbonImmutable::create($year, $month, 1, 0, 0, 0, $timezone);

        return $firstOfMonth->setDay(min($day, $firstOfMonth->daysInMonth));
    }

    private function today(Account $account): CarbonImmutable
    {
        return CarbonImmutable::today($account->timezone);
    }
}
