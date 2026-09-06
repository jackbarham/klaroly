<?php

namespace App\Support;

/**
 * One row of the enquiries list: a booking, the one event it is shown by, and
 * what else is already on that date.
 *
 * The first two are App\Support\BookingOccasion, reused rather than restated,
 * because "a booking and the event of it being shown" is a question the
 * contacts screen already answers and the two must not answer it differently.
 * App\Services\ContactActivity::mainEvent() makes the choice for both.
 *
 * The clash is not part of the occasion because it is not a property of the
 * booking at all: it is what every OTHER booking in the account is doing on
 * that date, so it can only be worked out for the whole payload at once. It
 * arrives here already computed, by App\Services\EnquiryClashes, which is what
 * keeps the resource free of the per-row query this field invites.
 *
 * Null when the date carries nothing else, when the enquiry has no date, and
 * when the enquiry is lost, because a lost enquiry has released the date.
 */
final class EnquiryRow
{
    public function __construct(
        public readonly BookingOccasion $occasion,
        public readonly ?ClashCounts $clash,
    ) {}
}
