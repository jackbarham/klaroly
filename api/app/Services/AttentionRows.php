<?php

namespace App\Services;

use App\Enums\AgreementStatus;
use App\Enums\EventType;
use App\Enums\WaitingOn;
use App\Models\Agreement;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Invoice;
use App\Support\AttentionRow;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The home screen's attention block, business logic 18.1: every booking that is
 * waiting on something, in decision 217's order.
 *
 * **It asks App\Services\WaitingOnResolver and takes what it says.** Feature
 * suppression is part of that calculation and not a filter over the top of it
 * (business logic 6 and 21): with invoicing off nothing is ever waiting on a
 * deposit, so the check never runs rather than running and having its answer
 * discarded. Filtering here afterwards would reintroduce the question and give
 * the home screen an opinion of its own about which values are reachable.
 *
 * It re-guards nothing either. A booking at lost or cancelled waits on nobody,
 * and that is answered at the top of the resolver rather than here.
 *
 * It reads relations the caller has already loaded and issues no queries. Load
 * what App\Http\Controllers\HomeController loads before calling it, or it is a
 * query per booking per relation: measured at 201 queries for forty live
 * bookings against 12 with the eager load.
 */
class AttentionRows
{
    public function __construct(private readonly WaitingOnResolver $resolver) {}

    /**
     * Every booking waiting on something, most urgent first.
     *
     * @param  Collection<int, Booking>  $bookings
     * @return array<int, AttentionRow>
     */
    public function for(Collection $bookings, CarbonImmutable $today): array
    {
        $rows = [];

        foreach ($bookings as $booking) {
            $waitingOn = $this->resolver->for($booking);

            if ($waitingOn === null) {
                continue;
            }

            $rows[] = $this->row($booking, $waitingOn, $today);
        }

        return $this->ordered($rows);
    }

    /**
     * Decision 217's precedence, then oldest first inside each value.
     *
     * **The order matters more here than it does on the enquiries list, and the
     * cap is why.** The phone previews the first four rows and the rest are
     * behind a See all, so an array grouped by party rather than by precedence
     * puts four of the artist's own rows at the top and an overdue balance can
     * never reach the preview. The screen groups by party afterwards, and each
     * group is then the surviving subsequence of this order rather than a
     * second rule to keep in step.
     *
     * @param  array<int, AttentionRow>  $rows
     * @return array<int, AttentionRow>
     */
    private function ordered(array $rows): array
    {
        usort($rows, function (AttentionRow $a, AttentionRow $b) {
            $rank = $a->waitingOn->rank() <=> $b->waitingOn->rank();

            if ($rank !== 0) {
                return $rank;
            }

            // Oldest first, on whichever timestamp the value is about, so the
            // most neglected of two equally urgent rows is above the other.
            $age = ($this->age($a) ?? '') <=> ($this->age($b) ?? '');

            // Without a final tie-break two rows with the same timestamp, or
            // two with none, could swap between identical requests.
            return $age !== 0 ? $age : $a->booking->id <=> $b->booking->id;
        });

        return $rows;
    }

    /**
     * The timestamp a value's own detail line is about, as a sortable string.
     *
     * Every value has one and they are all different, which is why this is a
     * match rather than a column: "how long it has been provisional" is
     * converted_at, "how many days late" is a due date, and "sent 11 days ago"
     * is on the agreement. Sorting them all on last_touched_at would put the
     * least overdue balance above the most overdue one.
     */
    private function age(AttentionRow $row): ?string
    {
        return match ($row->waitingOn) {
            WaitingOn::ArtistNotHeld => $row->booking->converted_at?->format('Y-m-d H:i:s'),
            // Both read the row's own earliest due date, which is what its
            // detail line says, so a booking carrying two overdue invoices
            // sorts on the older of them.
            WaitingOn::ClientBalance, WaitingOn::ClientDeposit => $row->dueOn(),
            WaitingOn::ArtistEnquiryCold => $row->booking->last_touched_at->format('Y-m-d H:i:s'),
            WaitingOn::ArtistPrice => $row->booking->created_at?->format('Y-m-d H:i:s'),
            WaitingOn::ClientSignature, WaitingOn::ClientForm => $row->agreement?->sent_at?->format('Y-m-d H:i:s'),
            // Unreachable while bookings.intake_available is false, and there
            // is no intake_forms table to carry a timestamp when it is not.
            WaitingOn::ArtistReview => $row->booking->last_touched_at->format('Y-m-d H:i:s'),
        };
    }

    private function row(Booking $booking, WaitingOn $waitingOn, CarbonImmutable $today): AttentionRow
    {
        return new AttentionRow(
            booking: $booking,
            waitingOn: $waitingOn,
            event: app(ContactActivity::class)->mainEvent($booking),
            trial: $this->trial($booking),
            invoices: $this->invoicesFor($booking, $waitingOn, $today),
            agreement: $this->agreementFor($booking, $waitingOn),
        );
    }

    /**
     * The trial, which artist_review's detail line names beside the wedding
     * day. Null on the great majority of bookings, which have one.
     */
    private function trial(Booking $booking): ?Event
    {
        return $booking->events->first(fn (Event $event) => $event->type === EventType::Trial);
    }

    /**
     * Every invoice a money row is about, earliest due date first. Empty on a
     * row that is not about invoices.
     *
     * **All of them, not the worst one** (decision 2026-09-06.2212). Schema
     * 5.15 lets a second invoice be raised on a booking manually, and the row
     * is one per booking, so naming one of two overdue invoices made the row's
     * money smaller than the booking's and the owed headline, which is the sum
     * of the rows, under-report by the difference. See App\Support\AttentionRow
     * for the figures this feeds.
     *
     * The predicates are the resolver's own, and the coupling is real: if its
     * balance() or deposit() changes what it matches, this has to follow.
     * Asking Invoice rather than the columns is what keeps that to one line
     * each.
     *
     * @return Collection<int, Invoice>
     */
    private function invoicesFor(Booking $booking, WaitingOn $waitingOn, CarbonImmutable $today): Collection
    {
        $live = $booking->invoices->filter(fn (Invoice $invoice) => $invoice->isIssued());

        return match ($waitingOn) {
            // The resolver's balance() condition exactly: past its balance due
            // date, still owing, and not snoozed.
            WaitingOn::ClientBalance => $live
                ->filter(fn (Invoice $invoice) => $invoice->balanceIsPastDue($today) && ! $invoice->isSnoozed($today))
                ->sortBy(fn (Invoice $invoice) => $invoice->balance_due_on->format('Y-m-d'))
                ->values(),

            WaitingOn::ClientDeposit => $live
                ->filter(fn (Invoice $invoice) => $invoice->deposit_minor->minor > 0 && ! $invoice->depositCovered())
                // Nulls last: a deposit with no due date is owed but not late,
                // so it must not sort above one that is and become the date the
                // row reports.
                ->sortBy(fn (Invoice $invoice) => $invoice->deposit_due_on?->format('Y-m-d') ?? '9999-12-31')
                ->values(),

            default => collect(),
        };
    }

    /**
     * The agreement waiting to be signed: the sent one at the highest version,
     * which is the one the client was actually asked to sign.
     */
    private function agreementFor(Booking $booking, WaitingOn $waitingOn): ?Agreement
    {
        if ($waitingOn !== WaitingOn::ClientSignature) {
            return null;
        }

        return $booking->agreements
            ->filter(fn (Agreement $agreement) => $agreement->status === AgreementStatus::Sent)
            ->sortByDesc('version')
            ->first();
    }
}
