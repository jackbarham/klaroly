<?php

namespace App\Enums;

use App\Enums\Concerns\HasCheckConstraint;

/**
 * Whether a booking discount is a minor-unit amount or a whole-number percentage.
 */
enum DiscountType: string
{
    use HasCheckConstraint;

    case Amount = 'amount';
    case Percent = 'percent';
}
