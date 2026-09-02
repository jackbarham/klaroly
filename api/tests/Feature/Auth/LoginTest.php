<?php

use App\Models\Account;
use App\Models\Booking;
use App\Models\User;

it('stores the email lowercase and accepts any case at login', function () {
    $this->postJson('/register', [
        'business_name' => 'Mixed Case Makeup',
        'name' => 'Mixed Case',
        'email' => 'Mixed@Example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertCreated();

    expect(User::sole()->email)->toBe('mixed@example.com');

    $this->postJson('/logout')->assertNoContent();

    $this->postJson('/login', ['email' => 'MIXED@EXAMPLE.COM', 'password' => 'correct-horse-battery'])
        ->assertOk()
        ->assertJson(['two_factor' => false]);

    $this->postJson('/logout')->assertNoContent();

    $this->postJson('/api/auth/token', [
        'email' => 'MIXED@EXAMPLE.COM',
        'password' => 'correct-horse-battery',
        'device_name' => 'Test phone',
    ])->assertOk()->assertJsonPath('me.user.email', 'mixed@example.com');
});

it('logs the demo owner in and binds her account', function () {
    $this->seed();

    $this->postJson('/login', ['email' => 'ellie@example.com', 'password' => config('demo.password')])
        ->assertOk()
        ->assertJson(['two_factor' => false]);

    $account = Account::where('username', 'elliemarsh')->sole();

    $this->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('data.account.id', $account->id)
        ->assertJsonPath('data.account.username', 'elliemarsh')
        ->assertJsonPath('data.user.email', 'ellie@example.com')
        ->assertJsonPath('data.membership.role', 'owner')
        ->assertJsonPath('data.features.enquiries', true);

    $seeded = Booking::withoutGlobalScope('account')->where('account_id', $account->id)->count();

    expect(currentAccount()->id())->toBe($account->id)
        ->and($seeded)->toBeGreaterThan(0)
        ->and(Booking::count())->toBe($seeded);
});

it('rejects a wrong password without saying which field was wrong', function () {
    createOwner(['email' => 'ellie@example.com']);

    $wrongPassword = $this->postJson('/login', ['email' => 'ellie@example.com', 'password' => 'nope']);
    $wrongEmail = $this->postJson('/login', ['email' => 'nobody@example.com', 'password' => 'password']);

    $wrongPassword->assertUnprocessable()->assertJsonValidationErrors(['email']);
    $wrongEmail->assertUnprocessable();

    expect($wrongPassword->getContent())->toBe($wrongEmail->getContent());
});

it('returns a JSON 401 for an API route with no credentials', function () {
    $this->getJson('/api/me')->assertUnauthorized();
});

it('sends a browser with no session to the web app login page', function () {
    // API routes always answer in JSON (see bootstrap/app.php). The redirect
    // is for Fortify's browser-facing routes, such as the verification link.
    $this->get('/email/verify/1/abc')->assertRedirect(config('app.frontend_url').'/login');
});
