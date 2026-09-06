<?php

namespace App\Services;

use App\Enums\AgreementStatus;
use App\Enums\BookingStage;
use App\Enums\FeatureKey;
use App\Enums\WaitingOn;
use App\Models\Booking;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Axis two of the booking lifecycle: what a booking is waiting on, and whose
 * court the ball is in. Business logic section 6.
 *
 * Computed, never stored (schema section 8). It takes a booking rather than an
 * event because a booking is what waits: two events of the same booking answer
 * the same. The Home attention block in business logic 18.1 is this same
 * calculation grouped by party, and will call this rather than write its own.
 *
 * Load agreements, invoices with their payments, quotes and account.settings
 * before calling this in a loop, or it is a query per booking.
 */
class WaitingOnResolver
{
    public function __construct(private readonly Features $features) {}

    /**
     * The one thing this booking is waiting on, or null when nothing is
     * outstanding.
     *
     * More than one can be true at once and the axis holds a single value, so
     * the order below is the answer and the first match wins:
     *
     * Nothing at all when the booking is archived, which is checked before the
     * list runs: lost and cancelled are both endings, and an ending waits on
     * nobody.
     *
     *   1. artist_not_held        the date itself could be lost
     *   2. client_balance         money overdue on work that is happening
     *   3. client_deposit         the date is not secured
     *   4. artist_enquiry_cold    a live enquiry going quiet
     *   5. artist_price           a client waiting on the artist for a price
     *   6. artist_review
     *   7. client_signature
     *   8. client_form
     *
     * Losing a date beats being owed money, and anything the artist can act on
     * alone beats waiting on a client.
     *
     * Cold sits above price deliberately, and it still has to after cold was
     * widened from Possible to all four live enquiry stages. The two overlap
     * at exactly one stage, Possible: an enquiry there with no quote against
     * it is both unpriced and, once it has sat long enough, cold. With price
     * above it, cold could never be reported at Possible at all, and Possible
     * is where most enquiries sit. Cold is also the more urgent reading of the
     * same row, because it says the artist has stopped replying rather than
     * merely not finished a sum.
     *
     * What the widening changed is what the pair means rather than the order.
     * They used to be two readings of one state, so the order was the only
     * thing making one of them reachable. Now cold answers a question about
     * three further stages where price never fires at all, so they are two
     * genuinely different questions that happen to collide at one stage, and
     * the order is what settles that collision.
     *
     * Balance and deposit stay above both. An enquiry can hold no invoice in
     * practice, because one is raised on conversion, so they rarely compete
     * for the same booking at all; where they did, money already overdue would
     * be the more urgent of the two, which is the order they are in.
     */
    public function for(Booking $booking): ?WaitingOn
    {
        // An archived record waits on nobody, because nobody is going to act
        // on it. A guard at the top rather than a filter at the bottom: the
        // question is answered here, and a caller discarding an answer it did
        // not want would be a second opinion held somewhere else. Without it a
        // lost enquiry carrying an agreement that was sent and never signed
        // reports client_signature, on a row the artist has already closed.
        if (in_array($booking->stage, [BookingStage::Lost, BookingStage::Cancelled], true)) {
            return null;
        }

        foreach ($this->checks() as $check) {
            $value = $check($booking);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * The checks in precedence order. A list rather than a chain of ifs, so
     * the order is a thing that can be read and reordered in one place.
     *
     * @return array<int, callable(Booking): ?WaitingOn>
     */
    private function checks(): array
    {
        return [
            $this->notHeld(...),
            $this->balance(...),
            $this->deposit(...),
            $this->enquiryCold(...),
            $this->price(...),
            $this->review(...),
            $this->signature(...),
            $this->form(...),
        ];
    }

    /**
     * A provisional booking whose hold has lapsed. The date is not held for
     * anybody and somebody else can take it.
     */
    private function notHeld(Booking $booking): ?WaitingOn
    {
        if ($booking->stage !== BookingStage::Provisional) {
            return null;
        }

        return $booking->holdHasExpired() ? WaitingOn::ArtistNotHeld : null;
    }

    /**
     * An issued invoice still owing something past its balance due date.
     */
    private function balance(Booking $booking): ?WaitingOn
    {
        if (! $this->invoicingIsOn($booking)) {
            return null;
        }

        $today = $this->today($booking);

        foreach ($this->liveInvoices($booking) as $invoice) {
            if ($invoice->balance_due_on !== null
                && $invoice->balance_due_on->lessThan($today)
                && $invoice->outstandingMinor() > 0) {
                return WaitingOn::ClientBalance;
            }
        }

        return null;
    }

    /**
     * An issued invoice whose deposit is not yet covered. Unlike the balance
     * this does not wait for a due date: until the deposit is paid the date is
     * not secured, which is what business logic 4.4 makes confirmation turn
     * on.
     */
    private function deposit(Booking $booking): ?WaitingOn
    {
        if (! $this->invoicingIsOn($booking)) {
            return null;
        }

        foreach ($this->liveInvoices($booking) as $invoice) {
            if ($invoice->deposit_minor->minor > 0 && ! $invoice->depositCovered()) {
                return WaitingOn::ClientDeposit;
            }
        }

        return null;
    }

    /**
     * A live enquiry that nobody has touched for a while. Business logic 5.2:
     * how long ago it was last touched is the fact that decides whether a
     * clash is worth a phone call.
     *
     * **Every live enquiry stage, not only Possible.** Firing only at Possible
     * was right while the home screen's attention block was the only consumer,
     * and it is wrong for GET /api/enquiries: a quote sent three weeks ago
     * with no reply, and a conversation that has gone silent, are both things
     * the artist has not done, which is what this axis is for, and both are
     * more actionable than most enquiries at Possible. Narrower than that, the
     * enquiries screen would have had to work cold out for itself and the
     * threshold would have had to reach the client, so the app would hold two
     * answers to "has this gone quiet".
     *
     * The stage set is Booking::ENQUIRY_STAGES, through isEnquiry(), rather
     * than four values listed here: they are the same four, and a fifth live
     * stage added to that constant has to reach this branch or an enquiry
     * could sit at it for ever without anybody being told.
     */
    private function enquiryCold(Booking $booking): ?WaitingOn
    {
        if (! $booking->isEnquiry()) {
            return null;
        }

        $days = (int) config('bookings.cold_enquiry_days');

        return $booking->last_touched_at->addDays($days)->isPast()
            ? WaitingOn::ArtistEnquiryCold
            : null;
    }

    /**
     * An enquiry the artist has decided is worth having and has not priced.
     *
     * Section 6 words the trigger as "reviewed, no quote built", which
     * presupposes an intake form to review. While there is no intake form in
     * existence there is nothing to review, so the precondition is met by the
     * artist's own act of moving the enquiry to Possible: that is her saying
     * it is worth pricing.
     *
     * It gates on whether intake is available and not on whether the artist
     * has switched it on, and the difference is not academic. The demo account
     * has every feature on, so under the toggle this branch stood down and an
     * enquiry at Possible with no quote, four days old, carried no pill at all
     * until it went cold three weeks later. That is the window in which
     * pricing it wins the job, and "Emma Clarke needs a price" is one of the
     * two examples in the Home mock-up in business logic 18.1.
     */
    private function price(Booking $booking): ?WaitingOn
    {
        if ($booking->stage !== BookingStage::Possible) {
            return null;
        }

        // Once there is a form to review, "reviewed, no quote built" is the
        // real condition and it goes here, replacing the fallback below.
        if ($this->intakeIsAvailable()) {
            return null;
        }

        return $booking->quotes->isEmpty() ? WaitingOn::ArtistPrice : null;
    }

    /**
     * A returned intake form the artist has not read.
     *
     * Unreachable, on purpose: there is no intake_forms table to ask, and
     * guessing from another column would be inventing an answer. The branch is
     * here so that the day section 7.4 lands there is one place to fill in,
     * and WaitingOnTest asserts it returns nothing so the silence is a
     * decision rather than an oversight.
     */
    private function review(Booking $booking): ?WaitingOn
    {
        if (! $this->intakeIsAvailable()) {
            return null;
        }

        // Reached only once bookings.intake_available is true, which is the
        // same change that migrates the table this needs. See the flag.
        return null;
    }

    /**
     * An agreement sent and not signed.
     */
    private function signature(Booking $booking): ?WaitingOn
    {
        if (! $this->agreementsAreOn($booking)) {
            return null;
        }

        $agreements = $booking->agreements;

        if ($agreements->contains(fn ($agreement) => $agreement->status === AgreementStatus::Signed)) {
            return null;
        }

        return $agreements->contains(fn ($agreement) => $agreement->status === AgreementStatus::Sent)
            ? WaitingOn::ClientSignature
            : null;
    }

    /**
     * An intake form sent and not returned. Unreachable for the same reason
     * as review() above; see that docblock.
     */
    private function form(Booking $booking): ?WaitingOn
    {
        if (! $this->intakeIsAvailable()) {
            return null;
        }

        return null;
    }

    /**
     * Today in the artist's own timezone, not the application's.
     *
     * APP_TIMEZONE is UTC, so today() is a UTC day, and for the last hour of a
     * British summer evening that is already tomorrow: a balance due today
     * would read as overdue while the artist looking at the calendar is still
     * on the day it is due.
     *
     * It matters here beyond being right on its own terms. App\Models\Invoice
     * asks the same question in isOverdue(), and GET /api/contacts asks it
     * through there. If only one of the two moved off UTC the app would hold
     * two answers to "is this overdue", and for that one hour the bookings
     * screen would say no while the contacts screen said yes.
     */
    private function today(Booking $booking): CarbonImmutable
    {
        return CarbonImmutable::today($booking->account->timezone);
    }

    /**
     * Invoices that can still be waited on: issued, and not voided. A draft
     * has no money on it until issue (schema 5.15) and a void one is not owed.
     *
     * @return Collection<int, Invoice>
     */
    private function liveInvoices(Booking $booking): Collection
    {
        return $booking->invoices->filter(fn ($invoice) => $invoice->isIssued());
    }

    /*
     * Suppression is part of the calculation and not a filter over the top of
     * it, per business logic section 6: with invoicing off, nothing is ever
     * waiting on a deposit, so the check never runs rather than running and
     * having its answer discarded. The resolver asks Features itself rather
     * than taking a flag, because a caller that can forget to pass one will.
     */

    private function invoicingIsOn(Booking $booking): bool
    {
        return $this->features->enabled($booking->account, FeatureKey::Invoicing, $booking);
    }

    private function agreementsAreOn(Booking $booking): bool
    {
        return $this->features->enabled($booking->account, FeatureKey::Agreements, $booking);
    }

    /**
     * Whether the intake form exists at all, which is a different question
     * from whether an artist has switched it on.
     *
     * Three branches read this and no branch asks Features about
     * FeatureKey::IntakeForms, on purpose: a feature that is switched on but
     * has no table behind it cannot send a form or receive one, so gating on
     * the toggle would gate on a promise the system cannot keep. One method
     * rather than three checks, because a three-place change is one that gets
     * half done.
     */
    private function intakeIsAvailable(): bool
    {
        return (bool) config('bookings.intake_available');
    }
}
