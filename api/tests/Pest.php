<?php

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\User;
use App\Support\CurrentAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Feature tests boot the application and run against the klaroly_test
// Postgres database, rebuilt once per run and wrapped in a transaction per
// test. Unit tests do not boot the application.
pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

/**
 * Create an account with a settings row and make it the current tenant.
 *
 * @param  array<string, mixed>  $settings
 */
function actingForAccount(array $settings = []): Account
{
    $account = Account::factory()->withSettings($settings)->create();

    app(CurrentAccount::class)->set($account);

    return $account->load('settings');
}

function currentAccount(): CurrentAccount
{
    return app(CurrentAccount::class);
}

/**
 * Create a user who owns an account with a settings row, the shape
 * registration produces, without going through the registration route.
 * The account is not bound as current; the request under test does that.
 *
 * @param  array<string, mixed>  $userAttributes
 */
function createOwner(array $userAttributes = [], ?Account $account = null): User
{
    $account ??= Account::factory()->withSettings()->create();

    $user = User::factory()->create($userAttributes + ['last_account_id' => $account->id]);

    AccountUser::factory()->owner()->create([
        'account_id' => $account->id,
        'user_id' => $user->id,
    ]);

    return $user;
}
