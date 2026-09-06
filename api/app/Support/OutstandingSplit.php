<?php

namespace App\Support;

/**
 * What the account is owed across every issued invoice, split by whether it is
 * late yet. Business logic 18.3 asks for outstanding "split into due and
 * overdue".
 *
 * **This is not App\Support\OutstandingAmount with a second field, and the two
 * stay separate on purpose.** That one describes one contact in one currency
 * and carries a boolean, because a contact row shows a single pill and any one
 * late invoice makes the amount a late amount. This is a headline that shows
 * two figures side by side, so the split has to be two amounts. Collapsing them
 * would give one shape a flag it reads differently in two places, which is what
 * App\Http\Resources\ContactBookingResource's own docblock rejects.
 *
 * **A snoozed invoice is counted as overdue, not as due.** The snooze
 * suppresses the chasing and not the fact, so calling that money "due" would
 * report a fortnight-late balance as if it were not late. $snoozedMinor is the
 * part of $overdueMinor the artist has asked to stop hearing about, so it is a
 * subset rather than a third bucket and the two must not be added together.
 *
 * All three are minor units of the account's currency; see
 * App\Support\MoneySummary for why the home block is filtered to one currency
 * rather than grouped by it.
 */
final class OutstandingSplit
{
    public function __construct(
        public readonly int $dueMinor,
        public readonly int $overdueMinor,
        public readonly int $snoozedMinor,
    ) {}

    public static function empty(): self
    {
        return new self(0, 0, 0);
    }
}
