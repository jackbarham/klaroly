<?php

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

it('resends the verification email to an unverified bearer caller', function () {
    Notification::fake();
    $user = createOwner(['email_verified_at' => null]);
    $token = $user->createToken('Phone');

    $this->withToken($token->plainTextToken)
        ->postJson('/api/auth/email/verification-notification')
        ->assertStatus(202);

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('sends nothing to a verified bearer caller', function () {
    Notification::fake();
    $user = createOwner(['email_verified_at' => now()]);
    $token = $user->createToken('Phone');

    $this->withToken($token->plainTextToken)
        ->postJson('/api/auth/email/verification-notification')
        ->assertNoContent();

    Notification::assertNothingSent();
});

it('answers a session caller the same way', function () {
    Notification::fake();

    $unverified = createOwner(['email_verified_at' => null]);
    $this->actingAs($unverified)->postJson('/api/auth/email/verification-notification')->assertStatus(202);
    Notification::assertSentTo($unverified, VerifyEmail::class);

    app('auth')->forgetGuards();
    currentAccount()->clear();

    $verified = createOwner(['email_verified_at' => now()]);
    $this->actingAs($verified)->postJson('/api/auth/email/verification-notification')->assertNoContent();
    Notification::assertNotSentTo($verified, VerifyEmail::class);
});
