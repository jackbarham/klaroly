<?php

namespace App\Services;

use App\Enums\FeatureKey;
use App\Models\Account;
use App\Models\Booking;

/**
 * The one reader of account_settings.features and bookings.feature_overrides.
 * A booking may switch a feature off (or on) for itself; otherwise the
 * account setting applies; a key that is absent from both maps is off
 * (decision 78). Registration writes the default map from
 * config/features.php so a new account starts with the right keys present.
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

        return false;
    }

    /**
     * Every key resolved for the account, which is the `features` map
     * GET /api/me and GET /api/home both send.
     *
     * @return array<string, bool>
     */
    public function map(Account $account): array
    {
        return collect(FeatureKey::cases())
            ->mapWithKeys(fn (FeatureKey $key) => [$key->value => $this->enabled($account, $key)])
            ->all();
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
