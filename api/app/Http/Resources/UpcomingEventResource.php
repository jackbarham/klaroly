<?php

namespace App\Http\Resources;

use App\Enums\FeatureKey;
use App\Http\Resources\Concerns\EventRowFields;
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
    use EventRowFields;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $booking = $this->booking;
        $travel = app(Features::class)->enabled($booking->account, FeatureKey::TravelEstimates, $booking);

        return [
            'event_id' => $this->id,
            ...$this->eventRowFields($booking),
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
            'travel_duration_s' => $travel ? $this->travel_duration_s : null,
            'travel_distance_m' => $travel ? $this->travel_distance_m : null,
        ];
    }

    private function partySize(): ?int
    {
        $count = $this->booking->partyMembers->count();

        return $count === 0 ? null : $count;
    }
}
