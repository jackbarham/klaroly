<?php

namespace App\Services;

use App\Models\Payment;
use App\Support\DateRange;
use App\Support\PeriodTotals;
use Carbon\CarbonImmutable;

/**
 * The one place "what came in" is answered.
 *
 * **Reported on the payment date and never on the invoice date.** That matches
 * cash basis accounting and therefore a sole trader's tax return, which is what
 * business logic 18.3 asks for and the reason this reads payments.paid_on
 * rather than joining anything to invoices.issued_on. A payment recorded in
 * September against an August invoice is September's money.
 *
 * Refunds are negative rows (schema 5.16) and are summed with everything else,
 * so a month whose refunds exceed its takings reports a negative figure. That
 * is the truthful answer to "what came in" and the screen can say so; netting
 * it to nought would hide the one month an artist most wants to look at.
 *
 * **One query for all four periods, bucketed here.** A query per period would
 * be four indexed aggregates rather than one, which is affordable, but the
 * count each period needs is a count of distinct bookings and that cannot be
 * added across periods afterwards. Fetching the rows once and bucketing them is
 * both cheaper and the only way the counts stay right.
 *
 * The query is built from Payment::query(), so the account global scope comes
 * with it. Nothing here writes where('account_id', ...) by hand and nothing
 * reaches for DB::table(): summing money reads like a query-builder job, and
 * written that way it totals every account's takings while looking perfectly
 * correct in a development database with one account in it.
 */
class PaymentsReceived
{
    /**
     * What came in during each of the given periods, in one currency.
     *
     * The currency comes from a join to bookings rather than from a column on
     * payments, because payments has none. Filtering to one currency rather
     * than grouping by it is schema section 8's other allowed shape for a money
     * tile; see App\Support\MoneySummary for why the headline takes it.
     *
     * @param  array<string, DateRange>  $periods
     * @return array<string, PeriodTotals>
     */
    public function forPeriods(array $periods, CarbonImmutable $earliest, string $currency): array
    {
        $rows = Payment::query()
            ->select('payments.amount_minor', 'payments.paid_on', 'payments.booking_id')
            // **The currency is selected off the join, and that is not
            // cosmetic.** payments has no currency column, so MoneyCast
            // resolves one through $payment->booking, and on rows fetched
            // without that relation it is a query per payment: the third time
            // this trap has been paid for, after booking.lines.booking and
            // booking.invoices.payments.booking. Selecting it here puts a
            // `currency` attribute on the row, which is the first thing the
            // cast looks for, so the lookup never happens. Cheaper than eager
            // loading the booking, because the join is already here.
            ->addSelect('bookings.currency')
            ->join('bookings', 'bookings.id', '=', 'payments.booking_id')
            // A join does not carry the joined model's scopes, so the soft
            // delete is written out: without it a payment against a deleted
            // booking would still be counted as money received.
            ->whereNull('bookings.deleted_at')
            ->where('bookings.currency', $currency)
            ->where('payments.paid_on', '>=', $earliest->toDateString())
            ->get();

        $totals = [];

        foreach ($periods as $name => $range) {
            $minor = 0;
            /** @var array<int, int> $bookings */
            $bookings = [];

            foreach ($rows as $row) {
                if (! $range->holds($row->paid_on)) {
                    continue;
                }

                $minor += $row->amount_minor->minor;
                // Keyed by booking id, so a wedding paid for in three
                // instalments is one booking in the count rather than three.
                $bookings[$row->booking_id] = $row->booking_id;
            }

            $totals[$name] = new PeriodTotals($range, $minor, count($bookings));
        }

        return $totals;
    }

    /**
     * Whether the account has taken money in any other currency since the
     * earliest boundary.
     *
     * One indexed existence check, so the payload can say the figures leave
     * something out rather than reporting a total that is quietly not the whole
     * story. See App\Support\MoneySummary.
     */
    public function hasOtherCurrencies(CarbonImmutable $earliest, string $currency): bool
    {
        return Payment::query()
            ->join('bookings', 'bookings.id', '=', 'payments.booking_id')
            ->whereNull('bookings.deleted_at')
            ->where('bookings.currency', '!=', $currency)
            ->where('payments.paid_on', '>=', $earliest->toDateString())
            ->exists();
    }
}
