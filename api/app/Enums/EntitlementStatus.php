<?php

namespace App\Enums;

use App\Enums\Concerns\HasCheckConstraint;

/**
 * Entitlement lifecycle, mirrored from the billing provider.
 */
enum EntitlementStatus: string
{
    use HasCheckConstraint;

    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Paused = 'paused';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
