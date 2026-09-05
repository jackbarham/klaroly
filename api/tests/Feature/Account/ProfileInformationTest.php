<?php

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

it('changes the name and the email', function () {
    Notification::fake();

    $user = createOwner(['name' => 'Ellie Marsh', 'email' => 'ellie@example.com']);

    $this->actingAs($user)
        ->putJson('/api/user/profile-information', [
            'name' => 'Ellie Marsh-Doyle',
            'email' => 'ellie.marsh@example.com',
        ])
        ->assertOk()
        ->assertJsonPath('data.user.name', 'Ellie Marsh-Doyle')
        ->assertJsonPath('data.user.email', 'ellie.marsh@example.com');

    expect($user->fresh()->name)->toBe('Ellie Marsh-Doyle')
        ->and($user->fresh()->email)->toBe('ellie.marsh@example.com');
});

it('un-verifies a changed email and sends a fresh verification email', function () {
    Notification::fake();

    $user = createOwner(['email' => 'ellie@example.com']);

    expect($user->email_verified_at)->not->toBeNull();

    $this->actingAs($user)
        ->putJson('/api/user/profile-information', [
            'name' => $user->name,
            'email' => 'new@example.com',
        ])
        ->assertOk()
        ->assertJsonPath('data.user.email_verified_at', null);

    expect($user->fresh()->email_verified_at)->toBeNull();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('leaves a verified address verified when only the name changes', function () {
    Notification::fake();

    $user = createOwner(['email' => 'ellie@example.com']);

    $this->actingAs($user)
        ->putJson('/api/user/profile-information', [
            'name' => 'Ellie M',
            'email' => 'ellie@example.com',
        ])
        ->assertOk();

    expect($user->fresh()->email_verified_at)->not->toBeNull();

    Notification::assertNothingSent();
});

it('refuses an email another user already has, in the flat validation shape', function () {
    createOwner(['email' => 'taken@example.com']);

    $user = createOwner(['email' => 'ellie@example.com']);

    $response = $this->actingAs($user)
        ->putJson('/api/user/profile-information', [
            'name' => $user->name,
            'email' => 'taken@example.com',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    expect($user->fresh()->email)->toBe('ellie@example.com');

    // Fortify's actions validate into a named error bag, and the app's
    // ApiError::validationErrors() reads a flat errors map. If the bag ever
    // reached the JSON, nothing else in either half would notice.
    $body = $response->json();

    expect(array_keys($body))->toBe(['message', 'errors'])
        ->and(array_keys($body['errors']))->toBe(['email'])
        ->and($body['errors']['email'])->toBeArray()
        ->and($body['errors']['email'][0])->toBeString();
});

it('stores a mixed-case email lowercase', function () {
    Notification::fake();

    $user = createOwner(['email' => 'ellie@example.com']);

    $this->actingAs($user)
        ->putJson('/api/user/profile-information', [
            'name' => $user->name,
            'email' => '  Mixed@Example.com ',
        ])
        ->assertOk()
        ->assertJsonPath('data.user.email', 'mixed@example.com');

    expect($user->fresh()->email)->toBe('mixed@example.com');
});

it('answers with exactly what the me endpoint answers', function () {
    Notification::fake();

    $user = createOwner();

    $updated = $this->actingAs($user)
        ->putJson('/api/user/profile-information', [
            'name' => 'Ellie Marsh-Doyle',
            'email' => 'ellie.marsh@example.com',
        ])
        ->assertOk()
        ->json();

    $me = $this->actingAs($user)->getJson('/api/me')->assertOk()->json();

    expect($updated)->toBe($me);
});

it('throttles the seventh update in a minute', function () {
    Notification::fake();

    $user = createOwner(['email' => 'ellie@example.com']);

    foreach (range(1, 6) as $attempt) {
        $this->actingAs($user)
            ->putJson('/api/user/profile-information', [
                'name' => "Ellie {$attempt}",
                'email' => 'ellie@example.com',
            ])
            ->assertOk();
    }

    $this->actingAs($user)
        ->putJson('/api/user/profile-information', [
            'name' => 'Ellie seven',
            'email' => 'ellie@example.com',
        ])
        ->assertTooManyRequests();
});
