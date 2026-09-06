<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Contact;
use App\Models\Invoice;
use App\Support\CurrentAccount;
use App\Support\Money;
use App\Support\OutstandingAmount;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The one place "what does this contact still owe" is answered.
 *
 * It does not work out an invoice's balance itself: App\Models\Invoice already
 * owns that, in outstandingMinor() and isOverdue(), with
 * tests/Feature/InvoiceDerivationTest.php behind them. This groups those
 * answers by currency, which is the part that was missing. Re-deriving the
 * balance here would be a second answer to a question that already has one,
 * the same way summing booking lines would be a second answer to the one
 * BookingPricing gives.
 *
 * It reads relations the caller has already loaded and issues no queries. Load
 * bookings.invoices.payments before calling it in a loop, and load
 * bookings.invoices.payments.booking as well: payments has no currency column,
 * so MoneyCast resolves a payment's currency through its booking and without
 * that second load it is a query per payment. That is the invisible N+1 the
 * events endpoint already learned about with booking.lines.booking.
 */
class OutstandingBalances
{
    public function __construct(private readonly CurrentAccount $account) {}

    /**
     * What this contact still owes, one entry per currency.
     *
     * Only issued invoices count. A draft has no money on it until issue
     * (schema 5.15) and a void one is not owed, so neither is money.
     *
     * A currency whose balance nets to zero or below is left out rather than
     * returned as a zero: nothing owed is an empty array, and a contact who
     * has overpaid is not owed money by the artist in any sense this screen
     * means. Every entry that comes back is a positive amount.
     *
     * Sorted by currency code, which is deterministic and obviously carries no
     * meaning. Nothing may depend on the order: is_account_currency is how the
     * client finds the entry it wants.
     *
     * @return array<int, OutstandingAmount>
     */
    public function for(Contact $contact): array
    {
        $accountCurrency = $this->account->require()->currency;
        $today = $this->today();

        /** @var array<string, array{minor: int, overdue: bool}> $totals */
        $totals = [];

        foreach ($contact->bookings as $booking) {
            foreach ($this->liveInvoices($booking) as $invoice) {
                $currency = $invoice->currency;

                $totals[$currency] ??= ['minor' => 0, 'overdue' => false];
                $totals[$currency]['minor'] += $invoice->outstandingMinor();

                // Overdue is a property of the currency's total here because
                // the row shows one pill: any one invoice being late makes the
                // amount a late amount. The invoice decides what late means,
                // in the artist's own day rather than in UTC.
                if ($invoice->isOverdue($today)) {
                    $totals[$currency]['overdue'] = true;
                }
            }
        }

        ksort($totals);

        $amounts = [];

        foreach ($totals as $currency => $total) {
            if ($total['minor'] <= 0) {
                continue;
            }

            $amounts[] = new OutstandingAmount(
                new Money($total['minor'], $currency),
                $total['overdue'],
                $currency === $accountCurrency,
            );
        }

        return $amounts;
    }

    /**
     * Invoices that can be owed on: issued, and not voided.
     *
     * @return Collection<int, Invoice>
     */
    private function liveInvoices(Booking $booking): Collection
    {
        return $booking->invoices->filter(fn (Invoice $invoice) => $invoice->isIssued());
    }

    /**
     * Today in the artist's own timezone, not the application's.
     *
     * APP_TIMEZONE is UTC, so today() is a UTC day. For the last hour of a
     * British summer evening that is already tomorrow, and an invoice due today
     * would be reported overdue while the artist looking at the screen is still
     * on the day it is due. A date comparison has to happen in the timezone the
     * date was written in.
     */
    private function today(): CarbonImmutable
    {
        return CarbonImmutable::today($this->account->require()->timezone);
    }
}
