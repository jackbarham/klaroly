<?php

use App\Models\User;

// Every write the My Account screen makes. All four sit in the same
// ['auth:sanctum', 'account'] group, and nothing else binds the tenant, so
// the two refusals below are the group's and not each route's.
dataset('my account routes', [
    'profile information' => ['put', '/api/user/profile-information'],
    'password' => ['put', '/api/user/password'],
    'account' => ['patch', '/api/account'],
    'marketing consent' => ['put', '/api/user/marketing-consent'],
]);

it('refuses a caller with no credential', function (string $method, string $path) {
    $this->json($method, $path, [])->assertUnauthorized();
})->with('my account routes');

it('refuses a user who belongs to no account', function (string $method, string $path) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->json($method, $path, [])
        ->assertForbidden()
        ->assertJson(['message' => __('account.no_membership')]);
})->with('my account routes');
