<?php

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

/**
 * Fortify's own /user/profile-information and /user/password run the same
 * two actions the twins under /api run, and nothing else in the suite
 * exercises them. Changing the current-password check to ask the user rather
 * than a named guard is the sort of change that fixes the phone and breaks
 * the browser, so both are checked here.
 */
it('runs the same profile action from Fortify own route', function () {
    Notification::fake();

    $user = createOwner(['email' => 'ellie@example.com']);

    $this->postJson('/login', ['email' => 'ellie@example.com', 'password' => 'password'])->assertOk();

    $this->putJson('/user/profile-information', [
        'name' => 'Ellie Marsh-Doyle',
        'email' => 'ellie@example.com',
    ])->assertOk();

    expect($user->fresh()->name)->toBe('Ellie Marsh-Doyle');
});

it('runs the same password action from Fortify own route, and keeps that session', function () {
    Notification::fake();

    $user = createOwner(['email' => 'ellie@example.com']);
    $keep = sessionRow($user);
    $other = sessionRow($user);

    actingAsWebApp($this, $user, $keep)
        ->putJson('/user/password', [
            'current_password' => 'wrong-one-entirely',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])->assertUnprocessable()->assertJsonValidationErrors(['current_password']);

    actingAsWebApp($this, $user, $keep)
        ->putJson('/user/password', [
            'current_password' => 'password',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])->assertOk();

    // The browser that made the change is still signed in; every other one
    // is not.
    expect(Hash::check('correct-horse-battery', $user->fresh()->password))->toBeTrue()
        ->and(DB::table('sessions')->pluck('id')->all())->toBe([$keep])
        ->and(DB::table('sessions')->where('id', $other)->exists())->toBeFalse();
});

/**
 * The third Fortify route the web app calls, and the one twin that shares no
 * action with it: both controllers ask the user whether the address is
 * verified and send the notification if not, so nothing but these two tests
 * holds the two answers together. useResendVerification() relies on all three
 * answers being the same on both paths.
 */
it('resends from Fortify own route the way the twin does', function () {
    Notification::fake();

    $user = createOwner(['email_verified_at' => null]);
    $session = sessionRow($user);

    actingAsWebApp($this, $user, $session)
        ->postJson('/email/verification-notification')
        ->assertStatus(202);
    actingAsWebApp($this, $user, $session)
        ->postJson('/api/auth/email/verification-notification')
        ->assertStatus(202);

    Notification::assertSentToTimes($user, VerifyEmail::class, 2);
});

it('sends nothing to a verified address from either route', function () {
    Notification::fake();

    $user = createOwner(['email_verified_at' => now()]);
    $session = sessionRow($user);

    actingAsWebApp($this, $user, $session)
        ->postJson('/email/verification-notification')
        ->assertNoContent();
    actingAsWebApp($this, $user, $session)
        ->postJson('/api/auth/email/verification-notification')
        ->assertNoContent();

    Notification::assertNothingSent();
});
