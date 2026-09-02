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
 *
 * reasonFor() is the single check. validate() turns its answer into a
 * validation message; the availability endpoint returns it as a code. The
 * two therefore cannot disagree.
 */
class Username implements ValidationRule
{
    public const PATTERN = '/^[a-z][a-z0-9]{2,62}$/';

    public const REASON_INVALID = 'invalid';

    public const REASON_RESERVED = 'reserved';

    public const REASON_TAKEN = 'taken';

    /**
     * @param  int|null  $exceptAccountId  history rows for this account do not block the name
     */
    public function __construct(private readonly ?int $exceptAccountId = null) {}

    /**
     * Why the name cannot be used, or null when it can.
     */
    public function reasonFor(mixed $value): ?string
    {
        if (! is_string($value) || preg_match(self::PATTERN, $value) !== 1) {
            return self::REASON_INVALID;
        }

        if (in_array($value, config('reserved_usernames'), true)) {
            return self::REASON_RESERVED;
        }

        $taken = UsernameHistory::query()
            ->where('username', $value)
            ->when($this->exceptAccountId !== null, fn ($query) => $query->where('account_id', '!=', $this->exceptAccountId))
            ->exists();

        return $taken ? self::REASON_TAKEN : null;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $key = match ($this->reasonFor($value)) {
            self::REASON_INVALID => 'validation.username.format',
            self::REASON_RESERVED => 'validation.username.reserved',
            self::REASON_TAKEN => 'validation.username.taken',
            null => null,
        };

        if ($key !== null) {
            $fail($key)->translate();
        }
    }
}
