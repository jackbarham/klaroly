<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\User;
use App\Support\CurrentAccount;

/**
 * Picks the account a user acts for and binds it as the current tenant
 * (decision 84). The order is: the user's last_account_id if they still
 * belong to it, otherwise their first membership by id, which is also their
 * only membership while there is one account per user.
 *
 * Both the BindCurrentAccount middleware and the token endpoint use this,
 * because the token endpoint answers with the same payload as GET /api/me
 * before any middleware has bound an account.
 */
class AccountResolver
{
    public function __construct(private readonly CurrentAccount $currentAccount) {}

    public function resolve(User $user): ?Account
    {
        $membership = null;

        if ($user->last_account_id !== null) {
            $membership = $this->memberships($user)
                ->where('account_id', $user->last_account_id)
                ->first();
        }

        if ($membership === null) {
            $membership = $this->memberships($user)->orderBy('id')->first();
        }

        if ($membership === null) {
            return null;
        }

        $account = $membership->account;

        $this->currentAccount->set($account);

        if ($user->last_account_id !== $account->id) {
            $user->forceFill(['last_account_id' => $account->id])->save();
        }

        return $account;
    }

    /**
     * The user's memberships across every account. account_user is a scoped
     * model and no account is bound yet, so the scope is lifted here. A
     * membership of a soft-deleted account does not count.
     */
    private function memberships(User $user)
    {
        return AccountUser::query()
            ->withoutGlobalScope('account')
            ->where('user_id', $user->id)
            ->whereHas('account');
    }
}
