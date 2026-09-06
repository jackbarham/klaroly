<?php

namespace App\Services;

use App\Enums\EventType;
use App\Models\Booking;
use App\Models\Contact;
use App\Models\Event;
use App\Support\BookingOccasion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Which of a contact's bookings is next, which was last, and which event of a
 * booking is the one to show.
 *
 * All of it reads the relations the controller has already loaded and issues
 * no queries of its own. Call it in a loop over a collection that was eager
 * loaded, or it becomes the N+1 the endpoint exists to avoid.
 *
 * **The event a booking shows depends on why it is being shown**, and that is
 * the whole reason this class exists rather than a method on Booking:
 *
 *   bookings[]     the main day, because the list of somebody's work is a list
 *                  of the jobs themselves and a trial is part of one of them.
 *   next_booking   the soonest future event of ANY type, because that row
 *                  answers "when do I next see this person". On 1 August, a
 *                  contact with a trial on the 15th and the wedding in
 *                  September reads "15 Aug, trial". Always taking the main
 *                  event would hide every trial from the one field that exists
 *                  to tell you about the next appointment.
 *   last_booking   the most recent past event of any type, for symmetry.
 *
 * The same next-and-last definition is written a second time, in SQL, in
 * ContactController::ordered(), because the ordering has to happen before the
 * limit and a PHP sort cannot do that. The two are kept in step by a test that
 * asserts the server's order is the order you get by sorting the payload's own
 * next and last fields; see ContactIndexTest.
 */
class ContactActivity
{
    /**
     * Every booking the contact has, each carrying its main day, newest first.
     *
     * The main event, or the earliest event when there is none: a standalone
     * trial and a commercial shoot are both bookings with no main day, and
     * showing them with no date at all would be worse than showing the date
     * they do have.
     *
     * @return array<int, BookingOccasion>
     */
    public function occasions(Contact $contact): array
    {
        return $contact->bookings
            ->map(fn (Booking $booking) => new BookingOccasion($booking, $this->mainEvent($booking)))
            // Newest first, ties broken by id, so two bookings on the same day
            // and two with no date at all cannot swap between identical
            // requests. A booking with no event sorts to the end, because an
            // empty string is below every date.
            ->sortByDesc(fn (BookingOccasion $occasion) => [
                $occasion->event?->event_date->format('Y-m-d') ?? '',
                $occasion->booking->id,
            ])
            ->values()
            ->all();
    }

    /**
     * The booking whose soonest event on or after today is soonest of all, and
     * that event. Null when the contact has nothing ahead of them.
     */
    public function next(Contact $contact, CarbonImmutable $today): ?BookingOccasion
    {
        return $this->pick($contact, fn (Event $event) => $event->event_date->format('Y-m-d') >= $today->toDateString(), ascending: true);
    }

    /**
     * The booking whose most recent event before today is the most recent of
     * all, and that event. Null when the contact has no history.
     */
    public function last(Contact $contact, CarbonImmutable $today): ?BookingOccasion
    {
        return $this->pick($contact, fn (Event $event) => $event->event_date->format('Y-m-d') < $today->toDateString(), ascending: false);
    }

    /**
     * The main event of a booking, or its earliest when it has none.
     */
    public function mainEvent(Booking $booking): ?Event
    {
        $events = $booking->events;

        return $events->first(fn (Event $event) => $event->type === EventType::Main)
            ?? $this->byDate($events)->first();
    }

    /**
     * @param  callable(Event): bool  $keep
     */
    private function pick(Contact $contact, callable $keep, bool $ascending): ?BookingOccasion
    {
        $events = $contact->bookings
            ->flatMap(fn (Booking $booking) => $booking->events)
            ->filter($keep);

        $sorted = $this->byDate($events);
        $event = $ascending ? $sorted->first() : $sorted->last();

        if ($event === null) {
            return null;
        }

        // The booking is taken from the loaded collection rather than through
        // $event->booking, which would be a query per contact for a relation
        // that is already in memory the other way round.
        $booking = $contact->bookings->firstWhere('id', $event->booking_id);

        return $booking === null ? null : new BookingOccasion($booking, $event);
    }

    /**
     * Events in date order, ties broken by id so the choice is the same twice
     * running. The date is compared as its own string rather than as an
     * instant: it is a local calendar date (schema 5.9) and turning it into a
     * moment to sort it is what puts an evening event on the wrong day.
     *
     * @param  Collection<int, Event>  $events
     * @return Collection<int, Event>
     */
    private function byDate(Collection $events): Collection
    {
        return $events
            ->sortBy(fn (Event $event) => [$event->event_date->format('Y-m-d'), $event->id])
            ->values();
    }
}
