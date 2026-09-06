<?php

namespace App\Support;

/**
 * What a contact still owes in one currency.
 *
 * A contact can owe in more than one, and schema section 8 is explicit that
 * money is grouped by currency and never summed across it, so the endpoint
 * returns a list of these rather than a figure and a code.
 *
 * `isAccountCurrency` is the field that keeps that list honest. The screen
 * shows one amount on a list row, and the obvious way to serve it would be to
 * sort the account's own currency first and let the client take the first
 * entry. That is a correctness contract carried by array position with nothing
 * asserting it, and it breaks silently the day somebody adds an ORDER BY for
 * an unrelated reason. With this flag the client selects the entry it wants
 * and the order becomes cosmetic.
 */
final class OutstandingAmount
{
    public function __construct(
        public readonly Money $amount,
        public readonly bool $isOverdue,
        public readonly bool $isAccountCurrency,
    ) {}
}
