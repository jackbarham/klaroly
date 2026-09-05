<?php

use App\Notifications\PasswordChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * A valid password change body.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function passwordChange(array $overrides = []): array
{
    return $overrides + [
        'current_password' => 'password',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ];
}

it('refuses a wrong current password from a token caller', function () {
    $user = createOwner();
    $token = $user->createToken('Ellie\'s iPhone');

    $this->withToken($token->plainTextToken)
        ->putJson('/api/user/password', passwordChange(['current_password' => 'not-the-password']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['current_password'])
        ->assertJsonPath('errors.current_password.0', __('auth.current_password_mismatch'));

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue()
        ->and(PersonalAccessToken::count())->toBe(1);
});

it('refuses a wrong current password from a session caller', function () {
    $user = createOwner();

    $this->actingAs($user)
        ->putJson('/api/user/password', passwordChange(['current_password' => 'not-the-password']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['current_password']);

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

it('refuses a password that does not meet the policy', function () {
    $user = createOwner();

    $this->actingAs($user)
        ->putJson('/api/user/password', passwordChange([
            'password' => 'short',
            'password_confirmation' => 'short',
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

it('tells a user with no password that there is nothing to confirm', function () {
    $user = createOwner(['password' => null]);

    $this->actingAs($user)
        ->putJson('/api/user/password', passwordChange())
        ->assertUnprocessable()
        ->assertJsonPath('errors.current_password.0', __('auth.no_password_set'));

    expect($user->fresh()->password)->toBeNull();
});

it('changes the password and keeps the token that made the request', function () {
    Notification::fake();

    $user = createOwner();
    $keep = $user->createToken('Ellie\'s iPhone');
    $revoked = $user->createToken('Ellie\'s iPad');

    $this->withToken($keep->plainTextToken)
        ->putJson('/api/user/password', passwordChange())
        ->assertOk()
        ->assertExactJson(['message' => __('auth.password_updated')]);

    expect(Hash::check('correct-horse-battery', $user->fresh()->password))->toBeTrue()
        ->and($user->tokens()->pluck('id')->all())->toBe([$keep->accessToken->id])
        ->and(PersonalAccessToken::find($revoked->accessToken->id))->toBeNull();

    // A fresh process would start with no guard and no tenant bound. The
    // phone is still signed in, which is the whole point of keeping it.
    app('auth')->forgetGuards();
    currentAccount()->clear();

    $this->withToken($keep->plainTextToken)->getJson('/api/me')->assertOk();
});

it('keeps the session that made the request and ends every other one', function () {
    Notification::fake();

    $user = createOwner();
    $keep = sessionRow($user);
    $other = sessionRow($user);
    $token = $user->createToken('Ellie\'s iPhone');

    actingAsWebApp($this, $user, $keep)
        ->putJson('/api/user/password', passwordChange())
        ->assertOk();

    expect(DB::table('sessions')->pluck('id')->all())->toBe([$keep])
        ->and(DB::table('sessions')->where('id', $other)->exists())->toBeFalse()
        // A session caller has no token to keep, so every token goes.
        ->and(PersonalAccessToken::find($token->accessToken->id))->toBeNull();
});

it('queues the password changed notification', function () {
    Notification::fake();

    $user = createOwner();

    $this->actingAs($user)
        ->putJson('/api/user/password', passwordChange())
        ->assertOk();

    Notification::assertSentTo($user, PasswordChanged::class);
});

it('throttles the seventh change in a minute', function () {
    $user = createOwner();

    foreach (range(1, 6) as $attempt) {
        $this->actingAs($user)
            ->putJson('/api/user/password', passwordChange(['current_password' => 'not-the-password']))
            ->assertUnprocessable();
    }

    $this->actingAs($user)
        ->putJson('/api/user/password', passwordChange(['current_password' => 'not-the-password']))
        ->assertTooManyRequests();
});
