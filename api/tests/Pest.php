<?php

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\User;
use App\Support\CurrentAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

/**
 * The same, for a collaborator: a member of the account who is not its
 * owner, which is what the permission checks turn on.
 *
 * @param  array<string, mixed>  $userAttributes
 */
function createCollaborator(array $userAttributes = [], ?Account $account = null): User
{
    $account ??= Account::factory()->withSettings()->create();

    $user = User::factory()->create($userAttributes + ['last_account_id' => $account->id]);

    AccountUser::factory()->create([
        'account_id' => $account->id,
        'user_id' => $user->id,
    ]);

    return $user;
}

/**
 * A session row for a user, as the database session driver would write one.
 * Returns its id so a test can name the session it expects to survive.
 */
function sessionRow(User $user, ?string $id = null): string
{
    $id ??= Str::random(40);

    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $user->id,
        'payload' => '',
        'last_activity' => now()->timestamp,
    ]);

    return $id;
}

/**
 * Make a request the way the web app makes one: signed in, credentialed, and
 * carrying a session cookie naming a row a test planted with sessionRow().
 *
 * It exists because getting any part of this wrong produces a test that
 * passes while proving the opposite of what it claims. A JSON request sends
 * no cookies at all unless it says it is credentialed, and Sanctum only
 * starts a session when the referer is one of its stateful domains. Without
 * both, request()->hasSession() is false, PasswordChanger is told to keep
 * nothing, and an assertion that the other session rows were deleted passes
 * because every row was deleted, including the one that should have
 * survived.
 *
 * The referer is the web app's own origin, which is also the host Sanctum is
 * configured to treat as stateful. Those are two separate environment
 * variables, so the one thing this helper cannot do is assume they still
 * agree: if they ever drift, it would silently stop starting a session and
 * quietly reintroduce the false green it was written to prevent. Hence the
 * check below, which fails loudly and names both settings.
 *
 * Fortify's own routes are in the web group and need no referer, but they
 * ignore the one this sends, so both halves of a parity test can be written
 * the same way.
 */
function actingAsWebApp(TestCase $test, User $user, string $sessionId): TestCase
{
    $host = parse_url(config('app.frontend_url'), PHP_URL_HOST);
    $stateful = array_map('trim', config('sanctum.stateful', []));

    TestCase::assertContains($host, $stateful, sprintf(
        'FRONTEND_URL is %s but SANCTUM_STATEFUL_DOMAINS is %s. Sanctum would not treat a request from the web app as stateful, no session would be started, and every test using this helper would pass without proving anything.',
        config('app.frontend_url'),
        implode(', ', $stateful),
    ));

    return $test->actingAs($user)
        ->withCredentials()
        ->withHeader('Referer', config('app.frontend_url'))
        ->withCookie(config('session.cookie'), $sessionId);
}

/**
 * A valid registration body. The mobile twin adds device_name through the
 * overrides.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function registration(array $overrides = []): array
{
    return $overrides + [
        'business_name' => 'Ellie Marsh Makeup',
        'name' => 'Ellie Marsh',
        'email' => 'ellie@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ];
}
