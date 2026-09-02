<?php

namespace App\Enums;

use App\Enums\Concerns\HasCheckConstraint;

/**
 * How an agreement was signed.
 */
enum SignedMethod: string
{
    use HasCheckConstraint;

    case Link = 'link';
    case Manual = 'manual';
}
