<?php

namespace App\Http\Controllers;

use App\Enums\BookingStage;
use App\Http\Requests\UpdateEnquiryStageRequest;
use App\Http\Resources\EnquiryDetailResource;
use App\Http\Resources\EnquiryResource;
use App\Models\Booking;
use App\Services\ContactActivity;
use App\Services\EnquiryClashes;
use App\Support\BookingOccasion;
use App\Support\ClashCounts;
use App\Support\EnquiryRow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;

/**
 * The enquiries screen's three routes: the list, one enquiry opened, and the
 * one write that moves a record between stages.
 *
 * They sit on one controller rather than three, the way EventController holds
 * both of the bookings screen's reads, because they share a stage set, an
 * eager-load list and the method that builds a row. show() and update() answer
 * with the same shape through the same private method, so the write's body and
 * the detail read's body are one code path rather than two a test has to hold
 * together.
 *
 * **There is no enquiries table and there never will be.** Business logic 4.3
 * is one bookings table with a stage column and every other field nullable,
 * and the interface shows enquiries and bookings as two lists filtered on
 * stage. This route returns bookings, and calling it /enquiries is the same
 * two-views-of-one-table framing rather than a second model.
 *
 * Scoped by the account global scope on Booking and Event, which the `account`
 * middleware binds before this runs. Nothing here writes where('account_id',
 * ...) by hand and nothing reaches for DB::table(): the clash counts in
 * particular read like a query-builder job, and written that way they cross
 * accounts while looking perfectly correct in a development database with one
 * account in it.
 */
class EnquiryController extends Controller
{
    /**
     * Everything a row, a detail or a write needs, loaded once rather than once
     * per relation per record.
     *
     * The two that look redundant are not. booking_lines and payments have no
     * currency column, so MoneyCast resolves theirs through the booking, and
     * without the extra hop that is a query per line and per payment.
     *
     * @var array<int, string>
     */
    private const ROW_RELATIONS = [
        'contact',
        'events',
        'lines',
        'lines.booking',
        // What WaitingOnResolver reads. An enquiry rarely has an invoice or an
        // agreement, but the resolver asks every booking and an unloaded
        // relation is a query whether or not it is empty.
        'quotes',
        'agreements',
        'invoices.payments',
        'invoices.payments.booking',
        'account.settings',
        // "Met at Elspeth Rowntree's wedding". A belongsTo on the scoped
        // Booking model, so the eager load carries the account scope with it.
        'sourceBooking.contact',
        'sourceBooking.events',
    ];

    /**
     * The three the detail adds. Loaded only there, because a notes load across
     * five hundred rows is the reason those fields are not on the list.
     *
     * @var array<int, string>
     */
    private const DETAIL_RELATIONS = [
        'partyMembers',
        'notes',
    ];

    /**
     * Every enquiry, most neglected first.
     *
     * **No parameters, no pagination and no filter**, for the reason
     * GET /api/contacts gives: the screen holds the whole list in memory and
     * does its own sorting, grouping and filtering with no round trip. A stage
     * parameter in particular is deliberately absent. The screen's groups are
     * the waiting-on axis and the staleness bands, not the stage, and a filter
     * the client could pass would be a second way of saying what the stage set
     * above already says.
     */
    public function index(): AnonymousResourceCollection
    {
        $maximum = (int) config('bookings.max_enquiries');

        // Counted before the fetch, and it is every enquiry rather than the
        // page, because the meta block has to be able to say how much was left
        // out. It is an indexed count: bookings (account_id, stage), schema
        // section 9.
        $total = Booking::query()->whereIn('stage', Booking::LISTED_STAGES)->count();

        $enquiries = $this->ordered()
            // Everything a row needs, loaded once for the whole page rather
            // than once per enquiry. Without this the endpoint issues a query
            // per enquiry per relation, which is invisible in a demo database
            // and ruinous in a real one.
            ->with(self::ROW_RELATIONS)
            ->limit($maximum)
            ->get();

        return EnquiryResource::collection($this->rows($enquiries))->additional([
            'meta' => [
                'total' => $total,
                'returned' => $enquiries->count(),
                // A flag rather than a 422, the same call GET /api/contacts
                // makes and the opposite of the events row cap. A caller that
                // sends no parameters cannot ask for less, so refusing would
                // leave that account with a dead screen. The ordering is what
                // makes it survivable: lost sorts last, so the first thing a
                // truncated response drops is the archive.
                'truncated' => $total > $maximum,
            ],
        ]);
    }

    /**
     * One enquiry as its own screen: the row, plus the message it arrived
     * with, the party size and the note stream.
     *
     * **Only a record the list would show.** A booking at provisional or
     * beyond is not found here, which is the same set GET /api/enquiries
     * returns, because this route is that row opened rather than a general
     * booking read. The booking screen gets its own endpoint.
     *
     * A 404 rather than a 422, unlike the write below, and the difference is
     * real: the write is asked to change a record and refuses because the
     * record is the wrong kind, so it has something to say about it. This is
     * asked for a resource that is not at this address.
     */
    public function show(Booking $booking): EnquiryDetailResource
    {
        abort_unless(in_array($booking->stage, Booking::LISTED_STAGES, true), 404);

        return $this->detail($booking);
    }

    /**
     * Move an enquiry to another stage, and record why when that stage is lost.
     *
     * **This route is the only way a record crosses the line between the two
     * lists** (decision 235). Nothing in the system promotes an enquiry on its
     * own: a deposit arriving does not convert one, and it could not, because a
     * deposit cannot arrive against a record with no invoice. Converting is the
     * artist's own tap, here.
     *
     * It sets a stage and two timestamps and nothing else. Dates, prices and
     * the contact are not reachable from here even by asking, because the
     * request names two fields and this reads only those.
     *
     * It answers with the detail shape rather than 204, so the screen replaces
     * what it is looking at rather than refetching. A record that has moved to
     * provisional is no longer an enquiry and comes back anyway: the screen
     * removes the row, which it can only do if it is told what happened.
     */
    public function update(UpdateEnquiryStageRequest $request, Booking $booking): EnquiryDetailResource
    {
        $stage = $request->stage();

        $booking->fill([
            'stage' => $stage,
            // Set on the way in and cleared on the way out. Converting is
            // reversible for as long as nothing has been signed (business logic
            // 5.3), and a converted_at left behind on a reversed conversion is a
            // lie in the audit trail. `?? now()` rather than `now()` so that
            // re-sending the same stage is not a second conversion.
            'converted_at' => $stage === BookingStage::Provisional
                ? ($booking->converted_at ?? now())
                : null,
            // The same rule for the other ending. Correcting the reason on a
            // record that is already lost keeps the original lost_at, because a
            // correction is not a second ending.
            'lost_at' => $stage === BookingStage::Lost
                ? ($booking->lost_at ?? now())
                : null,
            'lost_reason' => $stage === BookingStage::Lost ? $request->lostReason() : null,
        ]);

        // The one save, and the one place last_touched_at is written. See the
        // method on the model: every write path that touches a booking calls
        // it, and a writer that does not is a bug in the enquiries list rather
        // than in itself.
        $booking->touchActivity();

        return $this->detail($booking);
    }

    /**
     * The detail shape for one booking, loaded and composed the same way for
     * the read and for the write.
     *
     * One method rather than two, so the write's answer and the detail read's
     * answer are the same code rather than two paths a test has to hold
     * together.
     */
    private function detail(Booking $booking): EnquiryDetailResource
    {
        // load rather than loadMissing, because after a write the relations in
        // memory may predate it.
        $booking->load([...self::ROW_RELATIONS, ...self::DETAIL_RELATIONS]);

        $occasion = new BookingOccasion($booking, app(ContactActivity::class)->mainEvent($booking));
        $clashes = app(EnquiryClashes::class);

        $date = $occasion->event?->event_date->format('Y-m-d');
        $counts = $clashes->forDates($date === null ? [] : [$date]);

        return new EnquiryDetailResource(new EnquiryRow($occasion, $this->clashFor($clashes, $counts, $occasion)));
    }

    /**
     * Each enquiry paired with the event it is shown by and what else is on
     * that date.
     *
     * The clash counts are one query for every date in the payload, then
     * matched in memory. Counting them per row is the obvious N+1 and it is
     * the worse kind, because it grows with the number of distinct dates
     * rather than with the number of rows.
     *
     * @param  Collection<int, Booking>  $enquiries
     * @return array<int, EnquiryRow>
     */
    private function rows(Collection $enquiries): array
    {
        $activity = app(ContactActivity::class);
        $clashes = app(EnquiryClashes::class);

        /** @var array<int, BookingOccasion> $occasions */
        $occasions = $enquiries
            ->map(fn (Booking $booking) => new BookingOccasion($booking, $activity->mainEvent($booking)))
            ->all();

        $counts = $clashes->forDates(array_values(array_filter(array_map(
            fn (BookingOccasion $occasion) => $occasion->event?->event_date->format('Y-m-d'),
            $occasions,
        ))));

        return array_map(
            fn (BookingOccasion $occasion) => new EnquiryRow($occasion, $this->clashFor($clashes, $counts, $occasion)),
            $occasions,
        );
    }

    /**
     * What is on this enquiry's date, less the enquiry itself.
     *
     * Null with no date, and null when the enquiry is lost: a lost enquiry has
     * released the date, so counting what else wants it would be describing a
     * contest it has withdrawn from.
     *
     * @param  array<string, ClashCounts>  $counts
     */
    private function clashFor(EnquiryClashes $clashes, array $counts, BookingOccasion $occasion): ?ClashCounts
    {
        $event = $occasion->event;

        if ($event === null || $occasion->booking->stage === BookingStage::Lost) {
            return null;
        }

        return $clashes->forRow($counts, $event->event_date->format('Y-m-d'), $occasion->booking->stage);
    }

    /**
     * Enquiries in the order the screen would put them in: the ones nobody has
     * looked at, then the ones nobody has touched for longest.
     *
     * **Staleness is the default order, and New is pinned above it** (decision
     * 236). last_touched_at ascending is what the screen is for: business logic
     * promoted enquiries from a stage to a section because the artist is
     * talking to dozens of people about dates she will mostly not book and
     * cannot hold that in her head. But an enquiry at New has the freshest
     * timestamp in the list and would sort to the bottom, which is exactly
     * backwards, because it is the one nobody has looked at. So New sorts
     * above everything else, newest first, and staleness orders the rest.
     *
     * Lost sorts last, however it is ordered among itself, because the screen
     * groups it separately.
     *
     * The screen re-sorts anyway. This exists for the same two reasons the
     * contacts ordering does: it makes the response a total, stable order, so
     * two identical requests render identically, and it is what makes the
     * ceiling survivable, because a truncated response then drops the archive
     * rather than an arbitrary slice.
     *
     * @return Builder<Booking>
     */
    private function ordered(): Builder
    {
        $bucket = sprintf(
            "case when stage = '%s' then 0 when stage = '%s' then 2 else 1 end",
            BookingStage::New->value,
            BookingStage::Lost->value,
        );

        return Booking::query()
            ->whereIn('stage', Booking::LISTED_STAGES)
            ->orderByRaw($bucket.' asc')
            // Newest first, and only inside the New bucket: for every other row
            // this expression is null, so they are all equal on it and the next
            // key decides. Two keys rather than one because the two buckets are
            // ordered in opposite directions on the same column.
            ->orderByRaw(sprintf(
                "case when stage = '%s' then last_touched_at end desc",
                BookingStage::New->value,
            ))
            // Oldest first: the top of the list is the thing nobody has touched
            // for longest. The column is not null, so there is nothing to say
            // about nulls.
            ->orderBy('last_touched_at')
            // Without a final tie-break two enquiries touched at the same
            // instant could swap between identical requests.
            ->orderBy('id');
    }
}
