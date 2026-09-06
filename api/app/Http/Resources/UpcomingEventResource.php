<?php

namespace App\Http\Resources;

use App\Enums\FeatureKey;
use App\Models\Event;
use App\Services\Features;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One event in the home screen's Next up block, business logic 18.2: the
 * "what am I doing on Saturday" answer.
 *
 * **The unit is an event and not a booking**, the same as GET /api/events and
 * for the same reason: a booking's dates live in a separate table and normally
 * there are two of them, so a trial in March and the wedding in May are two
 * rows here. That is what 18.2 asks for, because a trial is a morning out of
 * the artist's diary exactly as the wedding is.
 *
 * @mixin Event
 */
class UpcomingEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $booking = $this->booking;

        return [
            'event_id' => $this->id,
            'booking_id' => $this->booking_id,
            // main or trial, mostly. The screen draws a Trial pill off this;
            // the wedding day is `main` and there is no `wedding` value.
            'type' => $this->type->value,
            'label' => $this->label,
            // A local calendar date, never an instant; see AttentionResource.
            'date' => $this->event_date->format('Y-m-d'),
            // 'HH:mm', and null when no call time is agreed. Sent without a
            // judgement about whether it is early: an early start is a fact
            // about a Saturday rather than a fault, and the prototype drew it
            // in a warning colour and then took it out for saying "problem" on
            // a row that only says "wedding".
            'start_time' => $this->start_time === null
                ? null
                : substr($this->start_time, 0, 5),
            // Nullable, which is four render cases and not three (decision
            // 228). The venue columns cannot tell "nobody has said" from "at
            // her own place, whose address lives in settings", because both are
            // a null venue_name and a null city.
            'location_type' => $this->location_type?->value,
            // Two fields rather than one line, which is what the prototype
            // asked for: "The Old Corn Exchange, Saffron Walden" truncates at
            // 375px, so the screen drops the town rather than the venue.
            'venue_name' => $this->venue_name,
            'city' => $this->city,
            'client_name' => $booking->contact->fullName(),
            'stage' => $booking->stage->value,
            // The party as a number, never as words. The screen writes "Bride
            // and 4" in its own locale file, and null at nought rather than a
            // nought: a party of nobody is not something anybody books, so the
            // figure could only mean "the party sheet is empty", which is "not
            // known yet" wearing a number. The same rule the enquiry detail's
            // party_size follows.
            'party_size' => $this->partySize(),
            // Seconds and metres, formatted at the edge like every other unit
            // here. Both are null until the travel work lands (schema 5.9 calls
            // them unused in v1), and null as well when the artist has travel
            // estimates switched off, because a figure from a feature she has
            // turned off is one she has said she does not want.
            'travel_duration_s' => $this->travel(FeatureKey::TravelEstimates) ? $this->travel_duration_s : null,
            'travel_distance_m' => $this->travel(FeatureKey::TravelEstimates) ? $this->travel_distance_m : null,
        ];
    }

    private function partySize(): ?int
    {
        $count = $this->booking->partyMembers->count();

        return $count === 0 ? null : $count;
    }

    private function travel(FeatureKey $key): bool
    {
        return app(Features::class)->enabled($this->booking->account, $key, $this->booking);
    }
}
