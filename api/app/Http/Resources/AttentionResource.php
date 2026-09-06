<?php

namespace App\Http\Resources;

use App\Support\AttentionRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the home screen's attention block.
 *
 * **No sentence and no day count.** The wording is British English in the app's
 * locale file: a server that writes UI copy is a server that has to be
 * redeployed to fix a typo. And every "9 days late" or "sent 11 days ago" on
 * this screen is worked out at render with differenceInCalendarDays against the
 * device's own calendar day, for the reason app/src/types/bookings.ts already
 * gives about a tab left open overnight. So this sends the raw material: dates
 * as dates, amounts as minor units, instants as instants.
 *
 * **Flat, with every key always present and null where it does not apply.** A
 * discriminated union nested under the value narrows more neatly in TypeScript
 * and cannot be asserted as a stable key list, which is what the twin in
 * tests/Pest.php exists to do. Flat is also what every other resource here
 * does.
 *
 * The fields each value's detail line needs, from the prototype:
 *
 *   artist_not_held        converted_at, hold_expires_at, event_date
 *   artist_enquiry_cold    stage, last_touched_at, event_date
 *   artist_price           created_at, event_date
 *   artist_review          trial_date, event_date
 *   client_balance         outstanding_minor, invoice_total_minor, due_on
 *   client_deposit         outstanding_minor, due_on, event_date
 *   client_signature       sent_at, event_date
 *   client_form            sent_at, event_date
 *
 * **The money figures are the BOOKING's, not one invoice's** (decision
 * 2026-09-06.2212). Schema 5.15 allows a second invoice to be raised manually,
 * so a booking with two overdue balances is a supported state, and the row was
 * always one per booking while its money was per invoice. They are summed on
 * App\Support\AttentionRow, which App\Http\Controllers\HomeController also
 * reads for the owed headline, so the headline and the sum of the rows agree by
 * construction rather than by a test.
 *
 * **outstanding_minor and due_on read against the balance or against the
 * deposit, and waiting_on says which.** That is one question, how much and by
 * when, asked of a different part of the booking's invoices, rather than a
 * field meaning two things: the row already carries the discriminator, and the
 * alternative is four keys of which two are always null.
 *
 * **No venue, and that is a finding rather than an omission.** It was on the
 * money rows first and is what pushed a fifth row past the fold at 375px: an
 * overdue balance is found by the name and the amount, and the venue there was
 * decoration.
 *
 * The shape is app/src/types/home.ts and the two must not drift, which is what
 * the key assertion in tests/Feature/Home/HomeIndexTest.php is for.
 *
 * @property-read AttentionRow $resource
 */
class AttentionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $row = $this->resource;
        $booking = $row->booking;
        $waitingOn = $row->waitingOn;

        return [
            // The booking's id, because that is what a row taps through to.
            'booking_id' => $booking->id,
            'waiting_on' => $waitingOn->value,
            // Whose court the ball is in, which is what 18.1 groups by. Sent
            // rather than parsed off the front of the value above; see
            // App\Enums\WaitingParty.
            'party' => $waitingOn->party()->value,
            'client_name' => $booking->contact->fullName(),
            'contact_id' => $booking->contact_id,
            'stage' => $booking->stage->value,
            'currency' => $booking->currency,

            // A local calendar date, never an instant. The column is a date and
            // the cast is immutable_date, so formatting it here cannot pass
            // through a timezone conversion; sending it as anything else would
            // move an evening event onto the wrong day for the eight months the
            // clocks are forward.
            'event_date' => $row->event?->event_date->format('Y-m-d'),
            'trial_date' => $row->trial?->event_date->format('Y-m-d'),

            'last_touched_at' => $booking->last_touched_at,
            // When the enquiry arrived, which is what artist_price's line says.
            'created_at' => $booking->created_at,
            // When it became provisional, which is "provisional, 16 days".
            'converted_at' => $booking->converted_at,
            // The hold that lapsed. A date, not an instant (schema 5.8).
            'hold_expires_at' => $booking->hold_expires_at?->format('Y-m-d'),
            // When the agreement went out. The intake form has no table yet, so
            // client_form cannot reach this and it is null there by definition
            // rather than by omission.
            'sent_at' => $row->agreement?->sent_at,

            // All three are the booking's, summed across every invoice the
            // value is about. "£540 of £1,200 · 16 days late" is a true
            // sentence about the booking, and the sixteen days is the oldest
            // overdue invoice on it, which is the number an artist says out
            // loud when chasing.
            'outstanding_minor' => $row->outstandingMinor(),
            'invoice_total_minor' => $row->invoiceTotalMinor(),
            'due_on' => $row->dueOn(),
        ];
    }
}
