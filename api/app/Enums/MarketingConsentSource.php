<?php

namespace App\Enums;

use App\Enums\Concerns\HasCheckConstraint;

/**
 * Where a user's marketing consent was given. The consent itself is the
 * dated users.marketing_consent_at column (decision 71); this says which
 * screen recorded it.
 */
enum MarketingConsentSource: string
{
    use HasCheckConstraint;

    case Portal = 'portal';
    case AppSignup = 'app_signup';
    case Settings = 'settings';
    case Other = 'other';
}
