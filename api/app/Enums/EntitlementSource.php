<?php

namespace App\Enums;

use App\Enums\Concerns\HasCheckConstraint;

/**
 * Where an entitlement came from. Manual lets an account in without a store object.
 */
enum EntitlementSource: string
{
    use HasCheckConstraint;

    case Stripe = 'stripe';
    case Apple = 'apple';
    case Google = 'google';
    case Manual = 'manual';
}
