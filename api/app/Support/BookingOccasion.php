<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Event;

/**
 * A booking, and the one event of it being shown.
 *
 * A booking normally has two events, a trial and the main day (schema 5.9),
 * and a contact row has room for one date. Which event that is depends on why
 * the booking is being shown, and this pair is what carries that decision from
 * App\Services\ContactActivity, which makes it, to ContactBookingResource,
 * which draws it.
 *
 * The alternative was three resources, or one resource with a flag it read
 * differently in three places. Both are ways for the three to drift; this is
 * one shape rendered one way, three times.
 *
 * The event is nullable because a booking need not have a date yet: an enquiry
 * that has arrived with no day named is a real row.
 */
final class BookingOccasion
{
    public function __construct(
        public readonly Booking $booking,
        public readonly ?Event $event,
    ) {}
}
