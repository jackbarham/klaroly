<?php

namespace App\Enums;

use App\Enums\Concerns\HasCheckConstraint;

/**
 * How the account deposit rule is expressed.
 */
enum DepositType: string
{
    use HasCheckConstraint;

    case Fixed = 'fixed';
    case Percent = 'percent';
}
