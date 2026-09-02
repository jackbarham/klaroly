<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

function mobileSessionRow(User $user): void
{
    DB::table('sessions')->insert([
        'id' => Str::random(40),
        'user_id' => $user->id,
        'payload' => '',
        'last_activity' => now()->timestamp,
    ]);
}

it('answers known and unknown addresses identically, and identically to the web route', function () {
    Notification::fake();
    $user = createOwner(['email' => 'ellie@example.com']);

    $known = $this->postJson('/api/auth/forgot-password', ['email' => 'ellie@example.com']);
    $unknown = $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com']);
    $web = $this->postJson('/forgot-password', ['email' => 'nobody@example.com']);

    $known->assertOk();
    $unknown->assertOk();
    $web->assertOk();

    expect($known->getContent())->toBe($unknown->getContent())
        ->and($known->getContent())->toBe($web->getContent());

    Notification::assertSentTo($user, ResetPassword::class);
    Notification::assertSentTimes(ResetPassword::class, 1);
});

it('throttles the fourth forgot-password request in a minute', function () {
    Notification::fake();

    foreach (range(1, 3) as $attempt) {
        $this->postJson('/api/auth/forgot-password', ['email' => 'ellie@example.com'])->assertOk();
    }

    $this->postJson('/api/auth/forgot-password', ['email' => 'ellie@example.com'])->assertTooManyRequests();
});

it('resets the password and signs every device and browser out without issuing a token', function () {
    $user = createOwner(['email' => 'ellie@example.com']);
    $user->createToken('Phone');
    $user->createToken('Tablet');
    mobileSessionRow($user);
    mobileSessionRow($user);

    $token = Password::broker()->createToken($user);

    $response = $this->postJson('/api/auth/reset-password', [
        'token' => $token,
        'email' => 'ELLIE@example.com',
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertOk()->assertJson(['message' => trans(Password::PASSWORD_RESET)]);

    expect($response->json())->not->toHaveKey('token')
        ->and(Hash::check('brand-new-password', $user->fresh()->password))->toBeTrue()
        ->and($user->tokens()->count())->toBe(0)
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and(auth()->guard('web')->check())->toBeFalse();
});

it('rejects a wrong token on the email field', function () {
    $user = createOwner(['email' => 'ellie@example.com']);

    $this->postJson('/api/auth/reset-password', [
        'token' => 'not-a-real-token',
        'email' => 'ellie@example.com',
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertUnprocessable()->assertJsonValidationErrors(['email']);

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});
