<?php

namespace App\Services;

use App\Enums\BookingStage;
use App\Models\Booking;
use App\Support\DateRange;
use App\Support\PeriodTotals;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The one place "what is a set of bookings worth" is answered: booked ahead,
 * provisional, and the period figure on an account that tracks no payments.
 *
 * It does not work out a booking's total itself. App\Services\BookingPricing
 * already owns that, honouring the pricing mode, the fixed price and both kinds
 * of discount, and summing the lines here would be a second answer to a
 * question that already has one — the same rule App\Services\OutstandingBalances
 * follows about an invoice's balance.
 *
 * **A booking counts under the date of its main day**, chosen by
 * App\Services\ContactActivity::mainEvent(), which is the same date the home
 * screen's attention row is shown by and the same one the enquiries row and the
 * contacts card use. One booking therefore cannot appear under two dates
 * anywhere in the app.
 *
 * That choice is the answer to a real question rather than a detail, because
 * the three candidates answer three different things: the main event date is
 * what the artist worked, created_at is what came in, and converted_at is what
 * was sold, and the count and the average change meaning with each. The main
 * date wins because booking value is the accrual twin of cash received and has
 * to look at the same window from the other side: the day an artist switches
 * payment tracking on, the two figures have to be comparable.
 *
 * **A booking with no event at all belongs to no period and is excluded from
 * every one of them.** That is a real row rather than an edge case: an enquiry
 * that arrived with no day named is normal, and one that has been priced and
 * converted before a date was settled is unusual but happens. Such a booking is
 * worth something and there is no honest period to put it in, so it appears in
 * none. It is still counted in booked ahead only once it has a date, for the
 * same reason.
 *
 * Everything is built from Booking::query(), so the account global scope comes
 * with it. Nothing writes where('account_id', ...) by hand.
 */
class BookingValue
{
    /**
     * The relations a total needs. `lines.booking` looks redundant beside
     * `lines` and is not: booking_lines has no currency column, so MoneyCast
     * resolves a line's currency through its booking, and without it that is a
     * query per line.
     *
     * @var array<int, string>
     */
    private const RELATIONS = ['events', 'lines', 'lines.booking'];

    public function __construct(
        private readonly BookingPricing $pricing,
        private readonly ContactActivity $activity,
    ) {}

    /**
     * What the bookings at one stage with a main day still ahead come to, and
     * how many there are.
     *
     * Provisional is asked for separately and is never added to the confirmed
     * figure (business logic 18.3): a held date is not money, and a figure that
     * mixed the two would be the most optimistic number in the app.
     *
     * @return array{minor: int, count: int}
     */
    public function ahead(BookingStage $stage, CarbonImmutable $today, string $currency): array
    {
        $bookings = $this->withDatesFrom($today)
            ->where('stage', $stage->value)
            ->where('currency', $currency)
            ->with(self::RELATIONS)
            ->get();

        return $this->sum($bookings, fn (?CarbonImmutable $date) => $date !== null
            && $date->format('Y-m-d') >= $today->format('Y-m-d'));
    }

    /**
     * What the weddings in each period were worth, priced rather than paid.
     *
     * The stage set is every stage that represents work the artist actually
     * has: an enquiry is not worth anything until it is converted, and lost and
     * cancelled are worth nothing by definition. Completed and closed are in,
     * because a period figure is mostly about work already done.
     *
     * @param  array<string, DateRange>  $periods
     * @return array<string, PeriodTotals>
     */
    public function forPeriods(array $periods, CarbonImmutable $earliest, CarbonImmutable $today, string $currency): array
    {
        $bookings = $this->withDatesBetween($earliest, $today)
            ->whereIn('stage', $this->earnedStages())
            ->where('currency', $currency)
            ->with(self::RELATIONS)
            ->get();

        $totals = [];

        foreach ($periods as $name => $range) {
            $totals[$name] = $this->period(
                $bookings,
                $range,
            );
        }

        return $totals;
    }

    /**
     * Whether the account holds work in any other currency, so the payload can
     * say the figures leave something out. See App\Support\MoneySummary.
     */
    public function hasOtherCurrencies(CarbonImmutable $earliest, string $currency): bool
    {
        return Booking::query()
            ->whereIn('stage', $this->earnedStages())
            ->where('currency', '!=', $currency)
            ->whereHas('events', fn (Builder $query) => $query->where('event_date', '>=', $earliest->toDateString()))
            ->exists();
    }

    /**
     * @param  Collection<int, Booking>  $bookings
     */
    private function period(Collection $bookings, DateRange $range): PeriodTotals
    {
        $totals = $this->sum($bookings, fn (?CarbonImmutable $date) => $date !== null && $range->holds($date));

        return new PeriodTotals($range, $totals['minor'], $totals['count']);
    }

    /**
     * The bookings whose main day passes the test, totalled.
     *
     * @param  Collection<int, Booking>  $bookings
     * @param  callable(?CarbonImmutable): bool  $keep
     * @return array{minor: int, count: int}
     */
    private function sum(Collection $bookings, callable $keep): array
    {
        $minor = 0;
        $count = 0;

        foreach ($bookings as $booking) {
            if (! $keep($this->activity->mainEvent($booking)?->event_date)) {
                continue;
            }

            $minor += $this->pricing->total($booking)->minor;
            $count++;
        }

        return ['minor' => $minor, 'count' => $count];
    }

    /**
     * Bookings holding at least one date from the given day onwards.
     *
     * It is whereHas rather than a filter in PHP because the alternative is
     * loading every booking the account has ever had in order to discard most
     * of them. A booking whose main day is behind it and whose only future
     * event is a gallery delivery survives this and is dropped by the main-day
     * test in sum(), which is a small over-fetch and the correct answer.
     *
     * @return Builder<Booking>
     */
    private function withDatesFrom(CarbonImmutable $from): Builder
    {
        return Booking::query()->whereHas(
            'events',
            fn (Builder $query) => $query->where('event_date', '>=', $from->toDateString()),
        );
    }

    /**
     * @return Builder<Booking>
     */
    private function withDatesBetween(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return Booking::query()->whereHas(
            'events',
            fn (Builder $query) => $query->whereBetween('event_date', [$from->toDateString(), $to->toDateString()]),
        );
    }

    /**
     * The stages that represent work the artist has, rather than work she might
     * get or work that went away.
     *
     * @return array<int, string>
     */
    private function earnedStages(): array
    {
        return array_map(fn (BookingStage $stage) => $stage->value, [
            BookingStage::Provisional,
            BookingStage::Confirmed,
            BookingStage::Completed,
            BookingStage::Closed,
        ]);
    }
}
