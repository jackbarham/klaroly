<?php

use App\Models\Account;

it('lets an owner rename the business', function () {
    $account = Account::factory()->withSettings()->create(['name' => 'Ellie Marsh Makeup']);
    $user = createOwner([], $account);

    $this->actingAs($user)
        ->patchJson('/api/account', ['name' => 'Marsh & Doyle Makeup'])
        ->assertOk()
        ->assertJsonPath('data.account.name', 'Marsh & Doyle Makeup');

    expect($account->fresh()->name)->toBe('Marsh & Doyle Makeup');
});

it('answers with exactly what the me endpoint answers', function () {
    $account = Account::factory()->withSettings()->create(['name' => 'Ellie Marsh Makeup']);
    $user = createOwner([], $account);

    $updated = $this->actingAs($user)
        ->patchJson('/api/account', ['name' => 'Marsh & Doyle Makeup'])
        ->assertOk()
        ->json();

    $me = $this->actingAs($user)->getJson('/api/me')->assertOk()->json();

    expect($updated)->toBe($me);
});

it('refuses a collaborator', function () {
    $account = Account::factory()->withSettings()->create(['name' => 'Ellie Marsh Makeup']);
    $user = createCollaborator([], $account);

    $this->actingAs($user)
        ->patchJson('/api/account', ['name' => 'Marsh & Doyle Makeup'])
        ->assertForbidden()
        ->assertJson(['message' => __('account.owner_only')]);

    expect($account->fresh()->name)->toBe('Ellie Marsh Makeup');
});

it('refuses a name longer than the column', function () {
    $account = Account::factory()->withSettings()->create(['name' => 'Ellie Marsh Makeup']);
    $user = createOwner([], $account);

    $this->actingAs($user)
        ->patchJson('/api/account', ['name' => str_repeat('a', 121)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    expect($account->fresh()->name)->toBe('Ellie Marsh Makeup');
});

it('requires a name', function () {
    $user = createOwner();

    $this->actingAs($user)
        ->patchJson('/api/account', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('leaves the account username untouched when the business name is renamed', function () {
    $account = Account::factory()->withSettings()->create([
        'name' => 'Ellie Marsh Makeup',
        'username' => 'elliemarshmakeup',
    ]);

    $user = createOwner([], $account);

    // The username is derived from the business name once, at registration.
    // A rename is a display change and must not carry through to it: the
    // username is a public profile address and moving it would release the
    // old one and write a username_history row.
    $this->actingAs($user)
        ->patchJson('/api/account', ['name' => 'Marsh & Doyle Makeup'])
        ->assertOk()
        ->assertJsonPath('data.account.name', 'Marsh & Doyle Makeup')
        ->assertJsonPath('data.account.username', 'elliemarshmakeup');

    expect($account->fresh()->username)->toBe('elliemarshmakeup')
        ->and($account->usernameHistory()->count())->toBe(1);
});
