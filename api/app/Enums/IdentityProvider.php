<?php

namespace App\Enums;

use App\Enums\Concerns\HasCheckConstraint;

/**
 * Third-party sign-in providers.
 */
enum IdentityProvider: string
{
    use HasCheckConstraint;

    case Apple = 'apple';
    case Google = 'google';
}
