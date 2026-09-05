<?php

namespace App\Http\Resources;

use App\Enums\FeatureKey;
use App\Models\User;
use App\Services\Features;
use App\Support\CurrentAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Who is logged in, which account they are acting for, their membership of
 * it, and the feature map. GET /api/me returns it and the token endpoint
 * embeds it, so the app has one shape to read.
 *
 * Requires the current account to be bound already. The membership is read
 * through the scoped AccountUser model, so it is the one for that account.
 *
 * @mixin User
 */
class MeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $account = app(CurrentAccount::class)->require();
        $features = app(Features::class);

        // Never null here: the account middleware refuses a user with no
        // membership before any controller runs, and TokenIssuer builds this
        // payload only after AccountResolver found one.
        $membership = $this->currentMembership();

        return [
            'user' => [
                'id' => $this->id,
                'uuid' => $this->uuid,
                'name' => $this->name,
                'email' => $this->email,
                'email_verified_at' => $this->email_verified_at,
                'notification_preferences' => $this->notification_preferences,
                'marketing_consent_at' => $this->marketing_consent_at,
            ],
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'username' => $account->username,
                'vertical' => $account->vertical,
                'country' => $account->country,
                'locale' => $account->locale,
                'currency' => $account->currency,
                'timezone' => $account->timezone,
                'profile_enabled' => $account->profile_enabled,
                'trial_ends_at' => $account->trial_ends_at,
            ],
            'membership' => [
                'role' => $membership->role->value,
                'can_edit' => $membership->can_edit,
                'can_see_prices' => $membership->can_see_prices,
                'can_see_invoices' => $membership->can_see_invoices,
                'can_see_contacts' => $membership->can_see_contacts,
            ],
            'features' => collect(FeatureKey::cases())
                ->mapWithKeys(fn (FeatureKey $key) => [$key->value => $features->enabled($account, $key)])
                ->all(),
        ];
    }
}
