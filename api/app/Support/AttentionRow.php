<?php

namespace App\Support;

use App\Enums\WaitingOn;
use App\Models\Agreement;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Invoice;
use Illuminate\Support\Collection;

/**
 * One row of the home screen's attention block: a booking, the one thing it is
 * waiting on, and the records that thing is about.
 *
 * **The money is the BOOKING's, not one invoice's** (decision 2026-09-06.2212).
 * Schema 5.15 allows a second invoice to be raised manually, so a booking with
 * two overdue balances is a state the product supports, and the row was always
 * one per booking while its money was per invoice. In that state the row named
 * one of the two and the owed headline, being the sum of the rows, under-
 * reported by the other.
 *
 * So $invoices is every invoice the value is about, and the three methods below
 * are the row's figures. "£540 of £1,200 · 16 days late" is then a true
 * sentence about the booking, and the sixteen days describes the oldest overdue
 * invoice on it, which is the number an artist says out loud when chasing.
 *
 * **Two things follow and both are the point.** The headline and the sum of the
 * rows agree by construction rather than by a test, because
 * App\Http\Controllers\HomeController sums these same methods. And "which of
 * two invoices does this row name" stops being a question, because the row
 * names none of them.
 *
 * The collection is empty on a row the value is not about invoices, which is
 * most of them. The agreement stays a single record: versions supersede each
 * other, so exactly one is the one the client was asked to sign.
 *
 * The events are the two dates the detail lines name: the main day, chosen by
 * App\Services\ContactActivity::mainEvent() so this and the enquiries row
 * cannot show one booking under two different dates, and the trial, which
 * artist_review's line asks for beside it.
 */
final class AttentionRow
{
    /**
     * @param  Collection<int, Invoice>  $invoices  ordered by the due date the value is about, earliest first
     */
    public function __construct(
        public readonly Booking $booking,
        public readonly WaitingOn $waitingOn,
        public readonly ?Event $event,
        public readonly ?Event $trial,
        public readonly Collection $invoices,
        public readonly ?Agreement $agreement,
    ) {}

    /**
     * What is owed on the part of the invoices this row is about: the whole
     * balance, or the shortfall against the deposit.
     *
     * The deposit figure is what is left of it rather than the deposit itself,
     * because a client who has paid half a deposit owes the half.
     *
     * Null rather than nought on a row that is not about money, so the screen
     * can tell "nothing owed" from "this row is not about an invoice".
     */
    public function outstandingMinor(): ?int
    {
        if ($this->invoices->isEmpty()) {
            return null;
        }

        $deposit = $this->waitingOn->isAboutTheDeposit();

        return (int) $this->invoices->sum(fn (Invoice $invoice) => $deposit
            ? max(0, $invoice->deposit_minor->minor - $invoice->paidMinor())
            : $invoice->outstandingMinor());
    }

    /**
     * What those invoices came to in total, which is the "of £1,200" half of
     * the sentence.
     */
    public function invoiceTotalMinor(): ?int
    {
        if ($this->invoices->isEmpty()) {
            return null;
        }

        return (int) $this->invoices->sum(fn (Invoice $invoice) => $invoice->total_minor->minor);
    }

    /**
     * The earliest due date among them, so "16 days late" describes the oldest
     * overdue invoice on the booking rather than an average of two.
     */
    public function dueOn(): ?string
    {
        $invoice = $this->invoices->first();

        if ($invoice === null) {
            return null;
        }

        $due = $this->waitingOn->isAboutTheDeposit()
            ? $invoice->deposit_due_on
            : $invoice->balance_due_on;

        return $due?->format('Y-m-d');
    }
}
