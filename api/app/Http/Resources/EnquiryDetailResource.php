<?php

namespace App\Http\Resources;

use App\Models\Booking;
use App\Models\Note;
use App\Support\EnquiryRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One enquiry as its own screen: the row, plus what a detail shows and a list
 * cannot afford to.
 *
 * **It composes EnquiryResource rather than repeating it.** The row half is
 * that resource's answer, resolved and spread, so the detail cannot disagree
 * with the list about which event this booking means, what it is waiting on or
 * what it clashes with. The two alternatives were both ways for it to. One
 * resource with the extra fields would make every row in a five-hundred-row
 * list pay for a notes load, or make the resource read a flag two ways, which
 * is what ContactBookingResource's own docblock rejects. Two independent
 * shapes would give the detail its own copy of three computed answers, and a
 * copy is a thing that drifts.
 *
 * The three extra fields are the ones the row deliberately leaves out.
 * enquiry_message is why this route exists: business logic 5.5.1 keeps the
 * source on the record so that when an extraction is wrong the artist can see
 * what it was working from, and it is the difference between a name from four
 * months ago and a conversation that can be picked up. It is also a pasted
 * WhatsApp thread, which is exactly why it is not on the list.
 *
 * The shape is app/src/types/enquiries.ts and the two must not drift, which is
 * what the key assertion in tests/Feature/Enquiries/EnquiryShowTest.php is
 * for: it asserts the list's own key constant followed by this file's three,
 * so neither half can move without the other.
 *
 * @property-read EnquiryRow $resource
 */
class EnquiryDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $booking = $this->resource->occasion->booking;

        return [
            ...EnquiryResource::make($this->resource)->resolve($request),

            // The original message, when the enquiry arrived with one. Null
            // for anything typed in during a phone call, which is most of what
            // an artist adds herself.
            'enquiry_message' => $booking->enquiry_message,
            'party_size' => $this->partySize($booking),
            'notes' => $this->notes($booking),
        ];
    }

    /**
     * How many people are having something done, or null when nobody has said.
     *
     * Null rather than zero, and the distinction is the field's whole value: a
     * party of nobody is not a thing anybody books, so a zero here can only
     * ever mean the party sheet is empty, which is "not known yet" wearing a
     * number. The screen says "5 people" or says nothing.
     */
    private function partySize(Booking $booking): ?int
    {
        $count = $booking->partyMembers->count();

        return $count === 0 ? null : $count;
    }

    /**
     * The private stream on this booking, newest first.
     *
     * **Booking notes only.** Schema 5.17 makes both `booking_id` and
     * `contact_id` nullable with a check that one of them is set, so a note can
     * belong to the person rather than to the job. That one is not a note about
     * this enquiry and belongs on the contact's card; `Booking::notes()` is
     * already the right relation and this does not widen it.
     *
     * `id` is here although the detail does not draw it. It is the row's
     * identity rather than a field, everything else in this payload carries one,
     * and a note nothing can refer to is the one thing here a later edit could
     * not address. No author, because collaborators do not exist in v1 and every
     * note is the owner's, and no `remind_at`, which is unused in v1.
     *
     * @return array<int, array<string, mixed>>
     */
    private function notes(Booking $booking): array
    {
        return $booking->notes->map(fn (Note $note) => [
            'id' => $note->id,
            'body' => $note->body,
            // A UTC instant, like last_touched_at, and for the same reason: a
            // day count worked out here would be wrong by the time a tab left
            // open overnight read it.
            'created_at' => $note->created_at,
        ])->all();
    }
}
