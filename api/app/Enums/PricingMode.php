<?php

namespace App\Enums;

use App\Enums\Concerns\HasCheckConstraint;

/**
 * Whether a booking is priced from its lines or as one fixed figure.
 */
enum PricingMode: string
{
    use HasCheckConstraint;

    case Itemised = 'itemised';
    case Fixed = 'fixed';
}
