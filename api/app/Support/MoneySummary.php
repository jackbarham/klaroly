<?php

namespace App\Support;

/**
 * The whole of the home screen's money block, business logic 18.3.
 *
 * **Two kinds of figure, and they must not be conflated.** The screen shows a
 * period selector above the block, and a selector above all of them would imply
 * it governs all of them. The periods below take one; owed, outstanding, booked
 * ahead and provisional are as of today and cannot, because outstanding is what
 * is unpaid right now and booked ahead is money in the future.
 *
 * **The block is never removed by a feature toggle** (business logic 21.2, read
 * properly). Switching invoicing or payment tracking off "removes the related
 * items from the home screen's attention block" — the attention block, named,
 * and nothing about the figures. A booking carries a price whether or not
 * anybody ever raised an invoice for it, so what a diary is worth is knowable
 * on every account. What the toggles take away is the cash half, one figure at
 * a time, and $basis says which half is being computed rather than leaving the
 * screen to infer it from an absent key.
 *
 * **Filtered to the account's currency rather than grouped by it, and it says
 * so.** Schema section 8 allows either and forbids only a bare SUM. A headline
 * cannot be an array, so this one is filtered, and $excludesOtherCurrencies is
 * what stops the figure being a silent lie on an account with a job abroad.
 */
final class MoneySummary
{
    /** Cash in, on the payment date. */
    public const BASIS_PAYMENTS = 'payments';

    /** What the weddings in the period were worth, priced rather than paid. */
    public const BASIS_BOOKING_VALUE = 'booking_value';

    /**
     * @param  array<string, PeriodTotals>  $periods  keyed by App\Services\BusinessPeriods' names
     */
    public function __construct(
        public readonly string $currency,
        public readonly string $basis,
        public readonly bool $excludesOtherCurrencies,
        public readonly array $periods,
        public readonly int $bookedAheadMinor,
        public readonly int $bookedAheadCount,
        public readonly int $provisionalMinor,
        public readonly int $provisionalCount,
        /*
         * Decision 27's headline, and null rather than nought when invoicing is
         * off: nothing was ever given a due date, so the question has no answer
         * on that account rather than an answer of nothing.
         *
         * It is the sum of the client_balance attention rows and not a second
         * query (decision 2026-09-06.1954). The row is a task with a name on it
         * and this is the size of the problem, and the day they disagree is the
         * day the screen shows two rows and a total that does not match them.
         */
        public readonly ?int $owedMinor,
        public readonly ?int $owedCount,
        /*
         * The late balance money a snooze has taken OUT of the headline above.
         *
         * **Decision 27's own reason for existing.** That decision says an
         * artist who can only stop the chasers by marking an invoice paid will
         * mark it paid, and the earnings figures then quietly become wrong. The
         * snooze is the honest escape hatch, and an escape hatch that silently
         * shrinks the headline teaches artists to distrust the headline
         * instead. So the money is still named, once, beside the figure it
         * left.
         *
         * Null on the same toggle as the headline.
         */
        public readonly ?int $snoozedMinor,
        /* Null for the same reason, and on the same toggle. */
        public readonly ?OutstandingSplit $outstanding,
    ) {}
}
