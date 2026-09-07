<?php

namespace App\Http\Resources\Concerns;

use App\Models\Booking;
use App\Models\Event;

/**
 * The ten keys an event row carries wherever one is drawn: on the bookings
 * screen and in the home screen's Next up block. One method rather than two
 * copies, because the 'HH:mm' rule and the location rule are each a decision,
 * and a decision written twice is one that drifts. The order is contractual:
 * both callers spread these straight after their own id key, and the key
 * assertions in EventIndexTest and HomeIndexTest pin it.
 *
 * @mixin Event
 */
trait EventRowFields
{
    /**
     * @return array<string, mixed>
     */
    private function eventRowFields(Booking $booking): array
    {
        return [
            'booking_id' => $this->booking_id,
            // main or trial, mostly. The wedding day is `main` and there is no
            // `wedding` value.
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
            // on an enquiry. Sent without a judgement about whether it is
            // early: an early start is a fact about a Saturday rather than a
            // fault, and the home prototype drew it in a warning colour and
            // then took it out for saying "problem" on a row that only says
            // "wedding".
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
            // Two fields rather than one line: "The Old Corn Exchange, Saffron
            // Walden" truncates at 375px, so the screen drops the town rather
            // than the venue.
            'venue_name' => $this->venue_name,
            'city' => $this->city,
            'client_name' => $booking->contact->fullName(),
            'stage' => $booking->stage->value,
        ];
    }
}
