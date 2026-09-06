<?php

namespace App\Http\Resources;

use App\Models\Contact;
use App\Services\ContactActivity;
use App\Services\OutstandingBalances;
use App\Support\CurrentAccount;
use App\Support\OutstandingAmount;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One contact on the contacts screen: who they are, how to reach them, their
 * bookings, and what they still owe.
 *
 * A contact is the person who books and pays and nothing else (schema 5.7):
 * one name, one email, ONE phone number and an address. Everybody else on the
 * day is a party member or a booking contact and neither belongs here.
 *
 * The shape is app/src/types/contacts.ts and the two must not drift, which is
 * what the key assertion in tests/Feature/Contacts/ContactIndexTest.php is
 * for.
 *
 * Four of the fields are computed rather than columns, all of them schema
 * section 8, and each is worked out by the service that owns that question
 * rather than here.
 *
 * @mixin Contact
 */
class ContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $activity = app(ContactActivity::class);
        $today = CarbonImmutable::today(app(CurrentAccount::class)->require()->timezone);

        $next = $activity->next($this->resource, $today);
        $last = $activity->last($this->resource, $today);

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            // Nullable in schema 5.7, and it stays nullable all the way out.
            // Somebody with one name is filed under that name by the screen,
            // never under a dash, and that only works if the API says so
            // rather than sending an empty string.
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address_line_1' => $this->address_line_1,
            'address_line_2' => $this->address_line_2,
            'city' => $this->city,
            'postcode' => $this->postcode,
            'country' => $this->country,

            // Every booking, newest first, each showing its main day. The
            // screen's detail card draws these; the list row never touches
            // them, which is what would let a future list payload leave the
            // array out entirely without any row breaking.
            'bookings' => ContactBookingResource::collection($activity->occasions($this->resource)),

            // All stages, so an enquiry that never became a booking still
            // counts as one. It is what the screen's delete refusal turns on:
            // schema 5.7 restricts bookings.contact_id, so a contact with any
            // booking against them cannot be removed.
            'booking_count' => $this->bookings->count(),

            // The soonest future event of any type and the most recent past
            // one, each with the booking it belongs to. Any type, not just the
            // main day: on 1 August a contact with a trial on the 15th and the
            // wedding in September should read "15 Aug, trial", because this
            // field answers when the artist next sees this person. Taking the
            // main event would hide every trial from the one field that exists
            // to say so.
            'next_booking' => $next === null ? null : new ContactBookingResource($next),
            'last_booking' => $last === null ? null : new ContactBookingResource($last),

            'outstanding' => $this->outstanding(),
        ];
    }

    /**
     * What this contact still owes, one entry per currency.
     *
     * An array rather than a figure and a code, because a contact who has had
     * one job abroad owes in two currencies and schema section 8 forbids
     * summing across them. Empty when nothing is owed: not null, and not a
     * zero entry.
     *
     * is_account_currency is what the screen selects on. It exists so that
     * nothing depends on the order of this array: "the account's currency is
     * first" would be a correctness contract carried by array position with
     * nothing asserting it, and it would break silently the first time
     * somebody added an ORDER BY for an unrelated reason.
     *
     * @return array<int, array<string, mixed>>
     */
    private function outstanding(): array
    {
        return array_map(fn (OutstandingAmount $owed) => [
            'currency' => $owed->amount->currency,
            // A bigint in the currency's minor unit beside its currency
            // (decision 77), never a float and never a formatted string.
            'amount_minor' => $owed->amount->minor,
            'is_overdue' => $owed->isOverdue,
            'is_account_currency' => $owed->isAccountCurrency,
        ], app(OutstandingBalances::class)->for($this->resource));
    }
}
