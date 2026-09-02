<?php

use App\Models\Account;

it('reports an invalid name', function () {
    $this->getJson('/api/usernames/Ellie')->assertOk()->assertExactJson(['available' => false, 'reason' => 'invalid']);
    $this->getJson('/api/usernames/ab')->assertOk()->assertJsonPath('reason', 'invalid');
});

it('reports a reserved name', function () {
    $this->getJson('/api/usernames/admin')->assertOk()->assertExactJson(['available' => false, 'reason' => 'reserved']);
});

it('reports a name held by a live account', function () {
    Account::factory()->create(['username' => 'elliemarsh']);

    $this->getJson('/api/usernames/elliemarsh')->assertOk()->assertExactJson(['available' => false, 'reason' => 'taken']);
});

it('reports a name an account once held', function () {
    $account = Account::factory()->create(['username' => 'oldname']);
    $account->update(['username' => 'newname']);

    $this->getJson('/api/usernames/oldname')->assertOk()->assertExactJson(['available' => false, 'reason' => 'taken']);
});

it('reports a free name', function () {
    $this->getJson('/api/usernames/freshname')->assertOk()->assertExactJson(['available' => true, 'reason' => null]);
});
