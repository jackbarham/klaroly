<?php

namespace App\Http\Controllers;

use App\Http\Requests\EventIndexRequest;
use App\Http\Resources\BookingEventResource;
use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * What the bookings screen reads: the events in a window, and the months that
 * hold any at all.
 *
 * Both are scoped by the account global scope on Event, which the `account`
 * middleware binds before either runs. Neither writes where('account_id', ...)
 * by hand: that would be the second source of truth the scope exists to
 * prevent, and it is the sort of line that goes on looking correct in a
 * development database with one account in it.
 */
class EventController extends Controller
{
    /**
     * The events between `from` and `to`, each carrying its booking's stage,
     * client, total and waiting-on state.
     *
     * Ordered by date, then start time with nulls last, then id. That is a
     * total order and a stable one: the list renders in exactly this sequence
     * and must not have to sort again, and without the final id two events at
     * the same time on the same day could swap between requests.
     */
    public function index(EventIndexRequest $request): AnonymousResourceCollection
    {
        $query = $this->inRange($request);

        $this->refuseIfTooMany($query);

        $events = $query
            ->with([
                // Everything a row needs, loaded once for the whole page
                // rather than once per event. Without this the endpoint issues
                // a query per event per relation, which is invisible in a demo
                // database and ruinous in a real one.
                'booking.contact',
                'booking.lines',
                // The lines' own way back to their booking, which looks
                // redundant beside the line above and is not. booking_lines
                // has no currency column, so MoneyCast resolves a line's
                // currency through $line->booking, and without this that is a
                // query per line rather than per request. One extra whereIn
                // buys back a query for every line in the response.
                'booking.lines.booking',
                'booking.quotes',
                'booking.agreements',
                'booking.invoices.payments',
                // The same trap as the line above, one level deeper. payments
                // has no currency column either, so summing what an invoice has
                // been paid resolves each payment's currency through its
                // booking. It only started costing anything when
                // Invoice::paidMinor() was taught to use the loaded relation:
                // before that it summed in the database and never touched the
                // cast, so the endpoint paid for a query per invoice instead
                // and this load would have bought nothing. One trap replaced
                // the other, and the eager load is what closes both.
                'booking.invoices.payments.booking',
                'booking.account.settings',
            ])
            ->orderBy('event_date')
            // Postgres sorts nulls last on an ascending column by default, but
            // saying so is what keeps this true if the column or the database
            // ever changes: an event with no call time belongs at the end of
            // its day, not the start of it.
            ->orderByRaw('start_time asc nulls last')
            ->orderBy('id')
            ->get();

        return BookingEventResource::collection($events);
    }

    /**
     * Every month the account holds at least one event in, for all time, as
     * 'YYYY-MM' strings.
     *
     * This is the month jump sheet's dots and the bounds of its year strip,
     * and nothing else. Presence, not counts: a count per month would serve a
     * year view that does not exist, and widening it later is a smaller change
     * than narrowing it.
     *
     * It has no parameters on purpose. Even for an artist ten years in this is
     * a few hundred bytes, and folding it into the windowed endpoint would
     * mean either no dots or fetching the whole diary to draw twelve of them.
     *
     * **It is invalidated by writes, not cached for a session.** Any write
     * that creates, moves or deletes an event date makes this stale, and the
     * missing dot is invisible until somebody reloads. Nothing on the bookings
     * screen can create a booking yet, which is exactly why the rule is
     * written here now, while the reason is still obvious.
     *
     * The distinct goes through the model rather than DB::table('events'). A
     * select distinct reads like a query-builder job, and written that way it
     * bypasses the global scope and returns every account's months while
     * looking perfectly correct in a single-account development database.
     */
    public function months(): JsonResponse
    {
        $months = Event::query()
            ->selectRaw("to_char(event_date, 'YYYY-MM') as month")
            ->distinct()
            ->orderBy('month')
            ->pluck('month')
            ->all();

        return response()->json(['data' => $months]);
    }

    /**
     * @return Builder<Event>
     */
    private function inRange(EventIndexRequest $request): Builder
    {
        $query = Event::query()->where('event_date', '>=', $request->from()->toDateString());

        if ($request->to() !== null) {
            $query->where('event_date', '<=', $request->to()->toDateString());
        }

        return $query;
    }

    /**
     * A cheap indexed count before the expensive fetch, so a deliberately wide
     * range is refused rather than served. It cannot fire on the call the app
     * makes by itself: `from` defaults to today, and nobody has two thousand
     * events ahead of them.
     *
     * @param  Builder<Event>  $query
     */
    private function refuseIfTooMany(Builder $query): void
    {
        $maximum = (int) config('bookings.max_events');

        if ($query->clone()->count() <= $maximum) {
            return;
        }

        throw ValidationException::withMessages([
            'from' => __('bookings.too_many_events', ['count' => $maximum]),
        ]);
    }
}
