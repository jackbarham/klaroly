<?php

namespace App\Support;

use App\Models\Account;
use RuntimeException;

/**
 * The account the current request or job is acting for.
 *
 * Bound as a container singleton in AppServiceProvider. Authentication sets
 * it once the user and their account are known; tests set it explicitly.
 * Every tenant-scoped model reads it through App\Models\Concerns\BelongsToAccount.
 */
class CurrentAccount
{
    private ?Account $account = null;

    public function set(Account $account): void
    {
        $this->account = $account;
    }

    public function get(): ?Account
    {
        return $this->account;
    }

    public function clear(): void
    {
        $this->account = null;
    }

    /**
     * The current account's id, or null when no account is bound.
     */
    public function id(): ?int
    {
        return $this->account?->id;
    }

    /**
     * The current account, or an exception when none is bound. For code
     * paths that cannot sensibly run without a tenant.
     */
    public function require(): Account
    {
        if ($this->account === null) {
            throw new RuntimeException('No current account is set.');
        }

        return $this->account;
    }
}
