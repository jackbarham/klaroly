<?php

use App\Models\Account;
use App\Models\Booking;
use App\Models\User;

it('shows each owner only their own account through the models', function () {
    $accountA = actingForAccount();
    $bookingA = Booking::factory()->create();
    $ownerA = createOwner(['email' => 'a@example.com'], $accountA);

    $accountB = Account::factory()->withSettings()->create();
    currentAccount()->set($accountB);
    $bookingB1 = Booking::factory()->create();
    $bookingB2 = Booking::factory()->create();
    $ownerB = createOwner(['email' => 'b@example.com'], $accountB);

    currentAccount()->clear();

    $this->postJson('/login', ['email' => 'a@example.com', 'password' => 'password'])->assertOk();
    $this->getJson('/api/me')->assertOk()->assertJsonPath('data.account.id', $accountA->id);

    expect(currentAccount()->id())->toBe($accountA->id)
        ->and(Booking::count())->toBe(1)
        ->and(Booking::find($bookingA->id))->not->toBeNull()
        ->and(Booking::find($bookingB1->id))->toBeNull();

    $this->postJson('/logout')->assertNoContent();

    // One test is one PHP process, so the sanctum guard and the tenant
    // singleton would otherwise remember the first request. Real requests
    // start clean.
    app('auth')->forgetGuards();
    currentAccount()->clear();

    $this->postJson('/login', ['email' => 'b@example.com', 'password' => 'password'])->assertOk();
    $this->getJson('/api/me')->assertOk()->assertJsonPath('data.account.id', $accountB->id);

    expect(currentAccount()->id())->toBe($accountB->id)
        ->and(Booking::count())->toBe(2)
        ->and(Booking::find($bookingA->id))->toBeNull()
        ->and(Booking::find($bookingB2->id))->not->toBeNull();
});

it('refuses a user who belongs to no account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/me')
        ->assertForbidden()
        ->assertJson(['message' => __('account.no_membership')]);

    expect(currentAccount()->id())->toBeNull();
});

it('falls back to the first membership when last_account_id is stale', function () {
    $gone = Account::factory()->withSettings()->create();
    $account = Account::factory()->withSettings()->create();
    $user = createOwner(['last_account_id' => $gone->id], $account);

    // The membership of the deleted account no longer counts.
    $gone->delete();

    $this->actingAs($user)->getJson('/api/me')->assertOk()->assertJsonPath('data.account.id', $account->id);

    expect($user->fresh()->last_account_id)->toBe($account->id);
});
