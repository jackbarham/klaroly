<?php

namespace App\Enums;

use App\Enums\Concerns\HasCheckConstraint;

/**
 * What the client has agreed to about photographs. Unused in the first build.
 */
enum PhotoConsent: string
{
    use HasCheckConstraint;

    case None = 'none';
    case Private = 'private';
    case Social = 'social';
}
