<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

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

it('resets the password and signs every device and browser out', function () {
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
});

it('changes the password and signs every other device and browser out', function () {
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
});
