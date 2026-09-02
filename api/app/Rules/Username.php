<?php

namespace App\Rules;

use App\Models\UsernameHistory;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A username becomes a hostname under klaroly.com, so it is checked in three
 * steps: the shape from decision 55, then the reserved list, then every name
 * anyone has ever held. Released names are never reclaimable by another
 * account, though an account may return to a name from its own history.
 */
class Username implements ValidationRule
{
    public const PATTERN = '/^[a-z][a-z0-9]{2,62}$/';

    /**
     * @param  int|null  $exceptAccountId  history rows for this account do not block the name
     */
    public function __construct(private readonly ?int $exceptAccountId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::PATTERN, $value) !== 1) {
            $fail('validation.username.format')->translate();

            return;
        }

        if (in_array($value, config('reserved_usernames'), true)) {
            $fail('validation.username.reserved')->translate();

            return;
        }

        $taken = UsernameHistory::query()
            ->where('username', $value)
            ->when($this->exceptAccountId !== null, fn ($query) => $query->where('account_id', '!=', $this->exceptAccountId))
            ->exists();

        if ($taken) {
            $fail('validation.username.taken')->translate();
        }
    }
}
