<?php

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
