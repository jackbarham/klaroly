<?php

namespace App\Http\Resources;

use App\Services\BookingPricing;
use App\Support\BookingOccasion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One of a contact's bookings, as a row on the contacts screen.
 *
 * The unit is a BOOKING and `id` is a booking's id, so a row links to the
 * booking rather than to one of its dates. But a booking has up to eight
 * events (schema 5.9) and this shape has room for one date, so the event it
 * carries is decided by App\Services\ContactActivity and handed over in a
 * BookingOccasion.
 *
 * That is why there is one resource here and not three. bookings[],
 * next_booking and last_booking are the same shape drawn three times, and the
 * only thing that differs between them is which event was chosen. Three
 * resources, or one resource reading a flag three ways, are both ways for the
 * three to answer differently.
 *
 * The shape is ContactBooking in app/src/types/contacts.ts and the two must
 * not drift, which is what the key assertion in
 * tests/Feature/Contacts/ContactIndexTest.php is for.
 *
 * @property-read BookingOccasion $resource
 */
class ContactBookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $booking = $this->resource->booking;
        $event = $this->resource->event;

        return [
            'id' => $booking->id,
            // Null when the booking has no date yet, which is a real state: an
            // enquiry can arrive before anybody has named a day. It cannot
            // happen on next_booking or last_booking, which are chosen by
            // having a date in the first place.
            'event_type' => $event?->type->value,
            // A local calendar date, never an instant. The column is a date and
            // the cast is immutable_date, so formatting it here cannot pass
            // through a timezone conversion; sending it as anything else would
            // move an evening event onto the wrong day for the eight months the
            // clocks are forward.
            'date' => $event?->event_date->format('Y-m-d'),
            'venue_name' => $event?->venue_name,
            'city' => $event?->city,
            'stage' => $booking->stage->value,
            // Through BookingPricing, which is the one place a booking's money
            // is worked out: it honours the pricing mode, the fixed price and
            // both kinds of discount. Summing the lines here would be a second
            // answer to a question that already has one.
            'total_minor' => app(BookingPricing::class)->total($booking)->minor,
            'currency' => $booking->currency,
        ];
    }
}
