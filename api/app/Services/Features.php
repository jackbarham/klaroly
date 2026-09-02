<?php

namespace App\Services;

use App\Enums\FeatureKey;
use App\Models\Account;
use App\Models\Booking;

/**
 * The one reader of account_settings.features and bookings.feature_overrides.
 * A booking may switch a feature off (or on) for itself; otherwise the
 * account setting applies; a key that has never been set is on.
 */
class Features
{
    public function enabled(Account $account, FeatureKey $key, ?Booking $booking = null): bool
    {
        if (! $this->hasActiveEntitlement($account)) {
            return false;
        }

        $overrides = $booking?->feature_overrides ?? [];

        if (array_key_exists($key->value, $overrides)) {
            return (bool) $overrides[$key->value];
        }

        $features = $account->settings?->features ?? [];

        if (array_key_exists($key->value, $features)) {
            return (bool) $features[$key->value];
        }

        return true;
    }

    /**
     * Whether the account is allowed in at all.
     *
     * TODO (billing prompt): read the entitlements table and return false for
     * expired, cancelled and paused accounts. Until then everyone is let in.
     */
    public function hasActiveEntitlement(Account $account): bool
    {
        return true;
    }
}
