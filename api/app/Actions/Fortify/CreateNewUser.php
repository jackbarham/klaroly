<?php

namespace App\Actions\Fortify;

use App\Enums\AccountRole;
use App\Enums\MarketingConsentSource;
use App\Models\Account;
use App\Models\AccountSettings;
use App\Models\AccountUser;
use App\Models\User;
use App\Rules\Username;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use RuntimeException;

/**
 * Registration (decision 84). One request creates the person, their
 * business and the membership joining the two, in one transaction, so a
 * failure anywhere leaves nothing behind. The entitlements row is not
 * created here; that is the billing prompt.
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Fallback base for a derived username when the business name has no
     * usable letters at all. Not on the reserved list.
     */
    private const FALLBACK_USERNAME = 'artist';

    /**
     * Deposit percentage a new account starts with. The account_settings
     * column defaults say "percent" but give no percentage, and the check
     * constraint insists on one, so registration has to choose. The artist
     * changes it on the settings screen.
     */
    private const DEFAULT_DEPOSIT_PERCENT = 25;

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        // The NormaliseEmail middleware does this on HTTP requests. Doing it
        // again here keeps the action safe when called from anywhere else.
        if (is_string($input['email'] ?? null)) {
            $input['email'] = mb_strtolower(trim($input['email']));
        }

        if (blank($input['username'] ?? null)) {
            $input['username'] = $this->deriveUsername((string) ($input['business_name'] ?? ''));
        }

        Validator::make($input, [
            'business_name' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'password' => $this->passwordRules(),
            'username' => ['required', 'string', new Username],
            'marketing_consent' => ['nullable', 'boolean'],
        ])->validate();

        $consented = filter_var($input['marketing_consent'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return DB::transaction(function () use ($input, $consented) {
            $account = Account::create([
                'name' => $input['business_name'],
                'username' => $input['username'],
                'vertical' => 'wedding_makeup',
                'country' => 'GB',
                'locale' => 'en-GB',
                'currency' => 'GBP',
                'timezone' => 'Europe/London',
                'trial_ends_at' => now()->addDays(config('billing.trial_days')),
            ]);

            AccountSettings::create([
                'account_id' => $account->id,
                'features' => config('features.defaults'),
                'deposit_percent' => self::DEFAULT_DEPOSIT_PERCENT,
            ]);

            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
                'marketing_consent_at' => $consented ? now() : null,
                'marketing_consent_source' => $consented ? MarketingConsentSource::AppSignup : null,
                'last_account_id' => $account->id,
            ]);

            AccountUser::create([
                'account_id' => $account->id,
                'user_id' => $user->id,
                'role' => AccountRole::Owner,
                'can_edit' => true,
                'can_see_prices' => true,
                'can_see_invoices' => true,
                'can_see_contacts' => true,
                'accepted_at' => now(),
            ]);

            return $user;
        });
    }

    /**
     * A username from the business name: lowercase, letters and digits only,
     * no leading digits. If that is too short, reserved or already held, a
     * number from 2 upwards is appended until the name passes the same rule
     * registration validates against.
     */
    private function deriveUsername(string $businessName): string
    {
        $base = preg_replace('/[^a-z0-9]/', '', mb_strtolower($businessName));
        $base = ltrim($base, '0123456789');

        if ($base === '') {
            $base = self::FALLBACK_USERNAME;
        }

        // Leave room for a suffix inside the 63-character hostname limit.
        $base = substr($base, 0, 58);

        $rule = new Username;

        if ($rule->reasonFor($base) === null) {
            return $base;
        }

        for ($suffix = 2; $suffix < 10000; $suffix++) {
            $candidate = $base.$suffix;

            if ($rule->reasonFor($candidate) === null) {
                return $candidate;
            }
        }

        throw new RuntimeException('Could not derive a free username from "'.$businessName.'".');
    }
}
