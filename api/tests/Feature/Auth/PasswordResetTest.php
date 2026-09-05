<?php

use App\Notifications\PasswordChanged;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('answers known and unknown addresses identically', function () {
    Notification::fake();
    $user = createOwner(['email' => 'ellie@example.com']);

    $known = $this->postJson('/forgot-password', ['email' => 'ellie@example.com']);
    $unknown = $this->postJson('/forgot-password', ['email' => 'nobody@example.com']);

    $known->assertOk();
    $unknown->assertOk();

    expect($known->getContent())->toBe($unknown->getContent());

    Notification::assertSentTo($user, ResetPassword::class);
    Notification::assertSentTimes(ResetPassword::class, 1);
});

it('links the reset email to the web app', function () {
    Notification::fake();
    $user = createOwner(['email' => 'ellie@example.com']);

    $this->postJson('/forgot-password', ['email' => 'Ellie@Example.com'])->assertOk();

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $url = $notification->toMail($user)->actionUrl;

        return str_starts_with($url, config('app.frontend_url').'/reset-password?token=')
            && str_contains($url, 'email=ellie%40example.com');
    });
});

it('throttles the fourth forgot-password request in a minute', function () {
    Notification::fake();

    foreach (range(1, 3) as $attempt) {
        $this->postJson('/forgot-password', ['email' => 'ellie@example.com'])->assertOk();
    }

    $this->postJson('/forgot-password', ['email' => 'ellie@example.com'])->assertTooManyRequests();
});

it('resets the password, signs every device and browser out and emails the account holder', function () {
    Notification::fake();
    $user = createOwner(['email' => 'ellie@example.com']);
    $user->createToken('Phone');
    $user->createToken('Tablet');
    sessionRow($user);
    sessionRow($user);
    $bystander = createOwner();
    sessionRow($bystander);

    $token = Password::broker()->createToken($user);

    $this->postJson('/reset-password', [
        'token' => $token,
        'email' => 'ELLIE@example.com',
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertOk();

    expect(Hash::check('brand-new-password', $user->fresh()->password))->toBeTrue()
        ->and($user->tokens()->count())->toBe(0)
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('sessions')->where('user_id', $bystander->id)->count())->toBe(1);

    Notification::assertSentTo($user, PasswordChanged::class);
    Notification::assertNotSentTo($bystander, PasswordChanged::class);
});

it('changes the password, signs every other device and browser out and emails the account holder', function () {
    Notification::fake();
    $user = createOwner();
    $user->createToken('Phone');
    $user->createToken('Tablet');
    $current = sessionRow($user);
    sessionRow($user);

    // JSON requests in the test client only carry cookies with credentials on.
    $this->withCredentials()
        ->withCookie(config('session.cookie'), $current)
        ->actingAs($user)
        ->putJson('/user/password', [
            'current_password' => 'password',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertOk();

    expect(Hash::check('brand-new-password', $user->fresh()->password))->toBeTrue()
        ->and($user->tokens()->count())->toBe(0)
        ->and(DB::table('sessions')->where('user_id', $user->id)->pluck('id')->all())->toBe([$current]);

    Notification::assertSentTo($user, PasswordChanged::class);
});

it('does not email the account holder when the reset token is wrong', function () {
    Notification::fake();
    $user = createOwner(['email' => 'ellie@example.com']);

    $this->postJson('/reset-password', [
        'token' => 'not-a-real-token',
        'email' => 'ellie@example.com',
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertUnprocessable();

    Notification::assertNothingSent();
});

it('links the password-changed email to the forgot-password screen', function () {
    Notification::fake();
    $user = createOwner(['email' => 'ellie@example.com']);
    $token = Password::broker()->createToken($user);

    $this->postJson('/reset-password', [
        'token' => $token,
        'email' => 'ellie@example.com',
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertOk();

    Notification::assertSentTo($user, PasswordChanged::class, function (PasswordChanged $notification) use ($user) {
        $mail = $notification->toMail($user);

        return $mail->actionUrl === config('app.frontend_url').'/forgot-password'
            && $mail->subject === __('mail.password_changed.subject');
    });
});
