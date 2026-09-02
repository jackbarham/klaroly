<?php

namespace App\Enums;

use App\Enums\Concerns\HasCheckConstraint;

/**
 * Where the artist works for an event: their own base, the client's address or a venue.
 */
enum LocationType: string
{
    use HasCheckConstraint;

    case Base = 'base';
    case Client = 'client';
    case Venue = 'venue';
}
