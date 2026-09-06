<?php

namespace App\Http\Resources;

use App\Models\Booking;
use App\Models\Event;
use App\Services\BookingPricing;
use App\Services\ContactActivity;
use App\Services\WaitingOnResolver;
use App\Support\EnquiryRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One enquiry on the enquiries screen.
 *
 * **The unit is the enquiry, not the event** (decision 234), which is the one
 * place this endpoint must not copy GET /api/events. That one returns a row
 * per event because the calendar's unit is a day. Here the unit is the
 * conversation, for two reasons that are both ordinary rather than edge cases:
 * an enquiry often has no date at all ("next summer, we have not booked the
 * venue yet" is normal and is one of the most winnable kinds there is), which
 * an events-shaped payload cannot represent because there is no row; and an
 * enquiry with a trial and a wedding is still one conversation, where two rows
 * would mean two staleness figures reading the same number and two chances to
 * reply twice to the same person.
 *
 * So `id` is the BOOKING's id, because that is what a row links to, and
 * `event` is one object rather than an array. Which event that is comes from
 * App\Services\ContactActivity::mainEvent(), the same choice the contacts
 * screen makes, so the two cannot show the same booking under different dates.
 *
 * The shape is app/src/types/enquiries.ts and the two must not drift, which is
 * what the key assertion in tests/Feature/Enquiries/EnquiryIndexTest.php is
 * for.
 *
 * @property-read EnquiryRow $resource
 */
class EnquiryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $booking = $this->resource->occasion->booking;
        $event = $this->resource->occasion->event;
        $reason = $booking->lost_reason;

        return [
            'id' => $booking->id,
            'stage' => $booking->stage->value,
            'client_name' => $booking->contact->fullName(),
            'contact_id' => $booking->contact_id,
            // How the enquiry arrived, as the key. What it is called on screen
            // is the app's decision, the same way a stage is.
            'source' => $booking->source?->value,
            'source_booking' => $this->sourceBooking($booking),
            // The UTC instant, never a number of days. A figure computed here
            // would be wrong by the time a tab left open overnight read it, and
            // the app works "eleven days ago" out against its own local
            // calendar day.
            'last_touched_at' => $booking->last_touched_at,
            // Through WaitingOnResolver, which is the one answer to this
            // question. Its enquiryCold() branch is what the screen's "Gone
            // quiet" group is: every row whose waiting_on is
            // artist_enquiry_cold. That is why the threshold is resolved here
            // and never reaches the client.
            'waiting_on' => app(WaitingOnResolver::class)->for($booking)?->value,
            // Through BookingPricing, the one place a booking's money is worked
            // out, and **null when nobody has priced it at all**, which is most
            // enquiries. Zero and no price are different facts and the screen
            // has to say so: "No price yet" against an enquiry nobody has
            // quoted, and "£0" against a job somebody is doing for nothing.
            // The predicate is isPriced() rather than a check on the lines
            // here, so there is one definition of priced.
            'total_minor' => $this->totalMinor($booking),
            'currency' => $booking->currency,
            'event' => $this->event($event),
            'lost_reason' => $reason?->value,
            // Sent rather than left for the client to map. The side is a fact
            // about the record and the label beside it is wording, and facts
            // come from the server.
            'lost_side' => $reason?->side()->value,
            'clash' => $this->clash(),
        ];
    }

    /**
     * What this enquiry comes to, or null when nobody has put a price on it.
     *
     * The currency is sent either way, because it is a fact about the booking
     * rather than about the price: a job in euros that nobody has quoted is
     * still a job in euros.
     */
    private function totalMinor(Booking $booking): ?int
    {
        $pricing = app(BookingPricing::class);

        return $pricing->isPriced($booking) ? $pricing->total($booking)->minor : null;
    }

    /**
     * The one date this enquiry is shown by, or null when nobody has named a
     * day.
     *
     * location_type is here for the reason GET /api/events already found: the
     * venue columns cannot tell "nobody has said where" from "at her own
     * place, whose address lives in settings", because both are a null venue
     * and a null city. Without it a trial at base reads as a wedding with a
     * missing venue.
     *
     * start_time is deliberately absent. An enquiry rarely has a call time
     * agreed and the row does not show one.
     *
     * @return array<string, mixed>|null
     */
    private function event(?Event $event): ?array
    {
        if ($event === null) {
            return null;
        }

        return [
            'type' => $event->type->value,
            // A local calendar date, never an instant. The column is a date and
            // the cast is immutable_date, so formatting it here cannot pass
            // through a timezone conversion; sending it as anything else would
            // move an evening event onto the wrong day for the eight months the
            // clocks are forward.
            'date' => $event->event_date->format('Y-m-d'),
            'location_type' => $event->location_type?->value,
            'venue_name' => $event->venue_name,
            'city' => $event->city,
        ];
    }

    /**
     * Enough of the booking this enquiry was captured at to say "met at
     * Elspeth Rowntree's wedding".
     *
     * One object rather than a bare id beside a nested copy of the same id:
     * there is one place to read it, and the date is chosen by the same
     * mainEvent() the row itself uses, so the two cannot disagree about which
     * day that booking was.
     *
     * @return array<string, mixed>|null
     */
    private function sourceBooking(Booking $booking): ?array
    {
        $source = $booking->sourceBooking;

        if ($source === null) {
            return null;
        }

        $event = app(ContactActivity::class)->mainEvent($source);

        return [
            'id' => $source->id,
            'client_name' => $source->contact->fullName(),
            'date' => $event?->event_date->format('Y-m-d'),
        ];
    }

    /**
     * What is already on this enquiry's date, or null.
     *
     * Null in three cases and they mean the same thing to a client asking "is
     * there a clash": the date carries nothing else, the enquiry has no date,
     * and the enquiry is lost. A lost enquiry has released the date, so
     * counting what else wants it would be describing a contest it has
     * withdrawn from.
     *
     * @return array<string, int>|null
     */
    private function clash(): ?array
    {
        $clash = $this->resource->clash;

        if ($clash === null || $clash->isEmpty()) {
            return null;
        }

        return [
            'confirmed' => $clash->confirmed,
            'provisional' => $clash->provisional,
            // Other enquiries at possible or quoted on the same date. Never
            // this row's own booking.
            'others' => $clash->others,
        ];
    }
}
