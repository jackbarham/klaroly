<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\EventRowFields;
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
    use EventRowFields;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $booking = $this->booking;
        $pricing = app(BookingPricing::class);

        return [
            'id' => $this->id,
            ...$this->eventRowFields($booking),
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
