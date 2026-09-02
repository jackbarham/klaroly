<?php

namespace App\Enums;

use App\Enums\Concerns\HasCheckConstraint;

/**
 * Who else is on the day besides the paying contact.
 */
enum BookingContactRole: string
{
    use HasCheckConstraint;

    case Partner = 'partner';
    case Planner = 'planner';
    case VenueCoordinator = 'venue_coordinator';
    case Emergency = 'emergency';
    case Other = 'other';
}
