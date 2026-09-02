<?php

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

function tokenRequest(array $overrides = []): array
{
    return $overrides + [
        'email' => 'ellie@example.com',
        'password' => 'password',
        'device_name' => 'Ellie\'s iPhone',
    ];
}

it('issues a token that authenticates the me endpoint', function () {
    $user = createOwner(['email' => 'ellie@example.com']);

    $response = $this->postJson('/api/auth/token', tokenRequest())
        ->assertOk()
        ->assertJsonStructure(['token', 'expires_at', 'me' => ['user', 'account', 'membership', 'features']])
        ->assertJsonPath('me.user.id', $user->id)
        ->assertJsonPath('me.account.id', $user->last_account_id);

    $this->withToken($response->json('token'))
        ->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.account.id', $user->last_account_id);

    $me = $this->withToken($response->json('token'))->getJson('/api/me')->json('data');

    expect($me)->toBe($response->json('me'))
        ->and(PersonalAccessToken::sole()->name)->toBe('Ellie\'s iPhone');
});

it('expires the token a year out', function () {
    createOwner(['email' => 'ellie@example.com']);

    $this->postJson('/api/auth/token', tokenRequest())->assertOk();

    $token = PersonalAccessToken::sole();

    expect($token->expires_at->toDateString())->toBe(now()->addDays(365)->toDateString())
        ->and(config('sanctum.expiration'))->toBe(365 * 24 * 60);
});

it('rejects wrong credentials without saying which field was wrong', function () {
    createOwner(['email' => 'ellie@example.com']);

    $wrongPassword = $this->postJson('/api/auth/token', tokenRequest(['password' => 'nope']));
    $wrongEmail = $this->postJson('/api/auth/token', tokenRequest(['email' => 'nobody@example.com']));

    $wrongPassword->assertUnprocessable()->assertJsonValidationErrors(['email'])->assertJsonMissingValidationErrors(['password']);
    $wrongEmail->assertUnprocessable();

    expect($wrongPassword->getContent())->toBe($wrongEmail->getContent())
        ->and(PersonalAccessToken::count())->toBe(0);
});

it('throttles the sixth attempt in a minute', function () {
    createOwner(['email' => 'ellie@example.com']);

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/auth/token', tokenRequest(['password' => 'nope']))->assertUnprocessable();
    }

    $this->postJson('/api/auth/token', tokenRequest())->assertTooManyRequests();
});

it('refuses a token for a user with no account', function () {
    User::factory()->create(['email' => 'ellie@example.com']);

    $this->postJson('/api/auth/token', tokenRequest())->assertForbidden();

    expect(PersonalAccessToken::count())->toBe(0);
});

it('tells a session caller to use logout instead of revoking a token', function () {
    $user = createOwner();

    $this->actingAs($user)->deleteJson('/api/auth/token')
        ->assertStatus(400)
        ->assertJson(['message' => __('auth.session_not_token')]);
});

it('revokes the token that made the request', function () {
    $user = createOwner();
    $token = $user->createToken('Phone');

    $this->withToken($token->plainTextToken)->deleteJson('/api/auth/token')->assertNoContent();

    expect(PersonalAccessToken::count())->toBe(0);
});

it('lists the caller\'s devices and marks the current one', function () {
    $user = createOwner();
    $phone = $user->createToken('Phone');
    $tablet = $user->createToken('Tablet');

    $response = $this->withToken($phone->plainTextToken)->getJson('/api/auth/tokens')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $byName = collect($response->json('data'))->keyBy('name');

    expect($byName['Phone']['current'])->toBeTrue()
        ->and($byName['Tablet']['current'])->toBeFalse()
        ->and($byName['Phone'])->toHaveKeys(['id', 'name', 'last_used_at', 'expires_at', 'created_at', 'current'])
        ->and($byName['Phone'])->not->toHaveKey('token');

    $this->withToken($phone->plainTextToken)
        ->deleteJson('/api/auth/tokens/'.$tablet->accessToken->id)
        ->assertNoContent();

    $this->withToken($phone->plainTextToken)->getJson('/api/auth/tokens')->assertJsonCount(1, 'data');
});

it('will not revoke another user\'s token', function () {
    $user = createOwner();
    $other = createOwner();
    $mine = $user->createToken('Phone');
    $theirs = $other->createToken('Phone');

    $this->withToken($mine->plainTextToken)
        ->deleteJson('/api/auth/tokens/'.$theirs->accessToken->id)
        ->assertNotFound();

    expect(PersonalAccessToken::count())->toBe(2);
});
