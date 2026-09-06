<?php

namespace App\Http\Controllers;

use App\Http\Resources\ContactResource;
use App\Models\Contact;
use App\Models\Event;
use App\Support\CurrentAccount;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * What the contacts screen reads: every contact the account holds, each with
 * its computed fields.
 *
 * Scoped by the account global scope on Contact and Event, which the `account`
 * middleware binds before this runs. Nothing here writes where('account_id',
 * ...) by hand and nothing reaches for DB::table(): the aggregates below read
 * like query-builder jobs, and written that way they cross accounts while
 * looking perfectly correct in a development database with one account in it.
 */
class ContactController extends Controller
{
    public function __construct(private readonly CurrentAccount $account) {}

    /**
     * Every contact, ordered by what is coming up.
     *
     * **No parameters, no pagination and no filter, and that is the design.**
     * The screen holds the whole list in memory and does its own sorting,
     * grouping and filtering with no round trip, which is what makes the filter
     * box instant and what makes the screen work with no signal. A page size or
     * a search parameter here would buy nothing and would take that away.
     */
    public function index(): AnonymousResourceCollection
    {
        $maximum = (int) config('contacts.max_contacts');

        // Counted before the fetch, and it is the whole table rather than the
        // page, because the meta block has to be able to say how much was left
        // out. It is an indexed count on a table that is small by definition.
        $total = Contact::query()->count();

        $contacts = $this->ordered()
            ->with([
                // Everything a contact needs, loaded once for the whole page
                // rather than once per contact. Without this the endpoint
                // issues a query per contact per relation, which is invisible
                // in a demo database and ruinous in a real one.
                'bookings.events',
                'bookings.lines',
                // The lines' own way back to their booking, which looks
                // redundant beside the line above and is not. booking_lines has
                // no currency column, so MoneyCast resolves a line's currency
                // through $line->booking, and without this that is a query per
                // line rather than per request.
                'bookings.lines.booking',
                'bookings.invoices.payments',
                // The same trap again, one level deeper and for the same
                // reason: payments has no currency column either, so summing
                // what has been paid resolves each payment's currency through
                // its booking. This is the load that keeps the money on this
                // screen free rather than a query per payment.
                'bookings.invoices.payments.booking',
            ])
            ->limit($maximum)
            ->get();

        return ContactResource::collection($contacts)->additional([
            'meta' => [
                'total' => $total,
                'returned' => $contacts->count(),
                // A flag rather than a 422. Refusing would leave the one
                // account with five thousand contacts looking at a dead screen,
                // and it could not ask for less because it sends nothing to ask
                // with. It gets the useful end of the list and is told there is
                // more.
                'truncated' => $total > $maximum,
            ],
        ]);
    }

    /**
     * Contacts in the order the screen would put them in: work ahead of you
     * first and soonest first, then history with the most recent at the top,
     * then everybody with neither.
     *
     * The ordering exists for two reasons and neither is the screen, which
     * re-sorts anyway. It makes the response a total, stable order, so two
     * identical requests render identically. And it is what makes the ceiling
     * survivable: because a contact with a future date sorts above every
     * contact without one, a truncated response is the useful end of the list
     * rather than an arbitrary slice.
     *
     * **This is the same next-and-last definition as
     * App\Services\ContactActivity, written a second time in SQL**, because the
     * ordering has to happen before the limit and a PHP sort cannot do that.
     * The two are held together by a test that asserts the server's order is
     * the order you get by sorting the payload's own next_booking and
     * last_booking; see ContactIndexTest.
     *
     * Both subqueries are built from Event::query(), so the account global
     * scope comes with them. A join does not carry the joined model's scopes,
     * which is why the soft-delete check on bookings is written out: without it
     * a deleted booking would still order its contact.
     *
     * @return Builder<Contact>
     */
    private function ordered(): Builder
    {
        $today = CarbonImmutable::today($this->account->require()->timezone)->toDateString();

        $nearest = fn (string $operator, string $direction) => Event::query()
            ->select('events.event_date')
            ->join('bookings', 'bookings.id', '=', 'events.booking_id')
            ->whereColumn('bookings.contact_id', 'contacts.id')
            ->whereNull('bookings.deleted_at')
            ->where('events.event_date', $operator, $today)
            ->orderBy('events.event_date', $direction)
            ->limit(1);

        return Contact::query()
            ->select('contacts.*')
            ->selectSub($nearest('>=', 'asc'), 'next_activity_on')
            ->selectSub($nearest('<', 'desc'), 'last_activity_on')
            // Postgres puts nulls last on an ascending sort and first on a
            // descending one, so saying it on both is what keeps this true if
            // the columns or the database ever change. A contact with nothing
            // ahead of them belongs below everybody who has something, not
            // above them.
            ->orderByRaw('next_activity_on asc nulls last')
            ->orderByRaw('last_activity_on desc nulls last')
            // Without a final tie-break two contacts with the same dates, or
            // two with no dates at all, could swap between identical requests.
            ->orderBy('contacts.id');
    }
}
