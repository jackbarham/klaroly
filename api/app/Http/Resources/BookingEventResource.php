<?php

namespace App\Http\Resources;

use App\Models\Event;
use App\Services\BookingPricing;
use App\Services\WaitingOnResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One event on the bookings screen, carrying enough of its booking to draw a
 * row and a mark on a day.
 *
 * The unit is an event and not a booking, because a booking's dates live in
 * a separate table (schema 5.9) and normally there are two of them, a trial
 * and the main day. Four of the fields below are per-booking rather than
 * per-event, so two events of one booking repeat the client, the stage, the
 * total and the waiting-on state. That is deliberate and it is what the app
 * reads: nesting a booking object inside would make the list sort, group and
 * filter through a level of indirection it never needs.
 *
 * The shape is app/src/types/bookings.ts and the two must not drift, which is
 * what the key assertion in tests/Feature/Bookings/EventIndexTest.php is for.
 *
 * @mixin Event
 */
class BookingEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $booking = $this->booking;
        $pricing = app(BookingPricing::class);

        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'type' => $this->type->value,
            // The artist's own name for this event, or null to let the app
            // fall back to the type's own name.
            'label' => $this->label,
            // A local calendar date, never an instant. The column is a date
            // and the cast is immutable_date, so formatting it here cannot
            // pass through a timezone conversion; sending it as anything else
            // would move an evening event onto the wrong day for the eight
            // months the clocks are forward.
            'date' => $this->event_date->format('Y-m-d'),
            // 'HH:mm'. Null when no call time is agreed yet, which is normal
            // on an enquiry.
            'start_time' => $this->start_time === null
                ? null
                : substr($this->start_time, 0, 5),
            // Where the artist works for this event: base, client or venue,
            // and null when nobody has said. The screen needs it because the
            // venue columns cannot tell the difference between "not known" and
            // "at her own place, whose address lives in settings": both are a
            // null venue_name and a null city, and a trial at base was reading
            // as a wedding with a missing venue.
            'location_type' => $this->location_type?->value,
            'venue_name' => $this->venue_name,
            'city' => $this->city,
            'client_name' => $booking->contact->fullName(),
            'stage' => $booking->stage->value,
            // Through BookingPricing, which is the one place a booking's money
            // is worked out: it honours the pricing mode, the fixed price and
            // both kinds of discount. Summing the lines here would be a second
            // answer to a question that already has one.
            'total_minor' => $pricing->total($booking)->minor,
            'currency' => $booking->currency,
            'waiting_on' => app(WaitingOnResolver::class)->for($booking)?->value,
            // The UTC instant, not a day count: a number computed here would
            // be wrong by the time an open tab reads it, and the app works out
            // "touched three weeks ago" against its own local calendar day.
            'last_touched_at' => $booking->last_touched_at,
        ];
    }
}
