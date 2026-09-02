<?php

namespace App\Enums;

use App\Enums\Concerns\HasCheckConstraint;

/**
 * How travel is charged on the rate card.
 */
enum TravelCharging: string
{
    use HasCheckConstraint;

    case Included = 'included';
    case Radius = 'radius';
    case PerMile = 'per_mile';
    case Flat = 'flat';
}
