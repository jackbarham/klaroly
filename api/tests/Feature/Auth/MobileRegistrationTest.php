<?php

use App\Models\Account;
use App\Models\AccountSettings;
use App\Models\AccountUser;
use App\Models\User;
use App\Models\UsernameHistory;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @return array<string, mixed>
 */
function mobileRegistration(array $overrides = []): array
{
    return $overrides + [
        'business_name' => 'Ellie Marsh Makeup',
        'name' => 'Ellie Marsh',
        'email' => 'ellie@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
        'device_name' => 'Ellie\'s iPhone',
    ];
}

it('registers and answers with a token that authenticates the me endpoint', function () {
    Notification::fake();

    $response = $this->postJson('/api/auth/register', mobileRegistration())
        ->assertCreated()
        ->assertJsonStructure(['token', 'expires_at', 'me' => ['user', 'account', 'membership', 'features']])
        ->assertJsonPath('me.account.username', 'elliemarshmakeup')
        ->assertJsonPath('me.membership.role', 'owner');

    expect($response->headers->get('Set-Cookie'))->toBeNull();

    $user = User::sole();

    expect(Account::count())->toBe(1)
        ->and(AccountSettings::withoutGlobalScope('account')->count())->toBe(1)
        ->and(AccountUser::withoutGlobalScope('account')->count())->toBe(1)
        ->and(UsernameHistory::count())->toBe(1)
        ->and(PersonalAccessToken::sole()->name)->toBe('Ellie\'s iPhone');

    Notification::assertSentTo($user, VerifyEmail::class);

    // A fresh process would start with no guard and no tenant bound.
    app('auth')->forgetGuards();
    currentAccount()->clear();

    $me = $this->withToken($response->json('token'))
        ->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('data.user.id', $user->id)
        ->json('data');

    expect($me)->toBe($response->json('me'));
});

it('requires a device name', function () {
    $this->postJson('/api/auth/register', mobileRegistration(['device_name' => null]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['device_name']);

    expect(User::count())->toBe(0)->and(Account::count())->toBe(0);
});

it('throttles the sixth registration in a minute', function () {
    Notification::fake();

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/auth/register', mobileRegistration([
            'email' => "artist{$attempt}@example.com",
            'business_name' => "Artist {$attempt}",
        ]))->assertCreated();
    }

    $this->postJson('/api/auth/register', mobileRegistration(['email' => 'six@example.com', 'business_name' => 'Six']))
        ->assertTooManyRequests();
});

it('stores the email lowercase', function () {
    Notification::fake();

    $this->postJson('/api/auth/register', mobileRegistration(['email' => 'Mixed@Example.com']))
        ->assertCreated()
        ->assertJsonPath('me.user.email', 'mixed@example.com');

    expect(User::sole()->email)->toBe('mixed@example.com');
});

it('leaves nothing behind and issues no token when the username is rejected', function () {
    $this->postJson('/api/auth/register', mobileRegistration(['username' => 'Not Valid']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['username']);

    expect(User::count())->toBe(0)
        ->and(Account::count())->toBe(0)
        ->and(UsernameHistory::count())->toBe(0)
        ->and(PersonalAccessToken::count())->toBe(0);
});

it('mints the same token as the token endpoint would for the same device', function () {
    Notification::fake();

    $this->postJson('/api/auth/register', mobileRegistration())->assertCreated();

    app('auth')->forgetGuards();
    currentAccount()->clear();

    $this->postJson('/api/auth/token', [
        'email' => 'ellie@example.com',
        'password' => 'correct-horse-battery',
        'device_name' => 'Ellie\'s iPhone',
    ])->assertOk();

    $tokens = PersonalAccessToken::orderBy('id')->get();

    expect($tokens)->toHaveCount(2)
        ->and($tokens[0]->name)->toBe($tokens[1]->name)
        ->and($tokens[0]->abilities)->toBe($tokens[1]->abilities)
        ->and($tokens[0]->expires_at->toDateString())->toBe($tokens[1]->expires_at->toDateString())
        ->and($tokens[0]->expires_at->toDateString())->toBe(now()->addDays(config('sanctum.token_expiry_days'))->toDateString());
});
