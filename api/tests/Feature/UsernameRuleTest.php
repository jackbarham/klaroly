<?php

use App\Models\Account;
use App\Models\UsernameHistory;
use App\Rules\Username;
use Illuminate\Support\Facades\Validator;

function usernameFails(string $value, ?int $exceptAccountId = null): bool
{
    return Validator::make(['username' => $value], ['username' => [new Username($exceptAccountId)]])->fails();
}

it('accepts a well-formed, unclaimed name', function () {
    expect(usernameFails('elliemarsh'))->toBeFalse()
        ->and(usernameFails('abc'))->toBeFalse()
        ->and(usernameFails('a1234'))->toBeFalse();
});

it('rejects the wrong shape', function () {
    expect(usernameFails('EllieMarsh'))->toBeTrue()
        ->and(usernameFails('ellie-marsh'))->toBeTrue()
        ->and(usernameFails('ellie_marsh'))->toBeTrue()
        ->and(usernameFails('1ellie'))->toBeTrue()
        ->and(usernameFails('ab'))->toBeTrue()
        ->and(usernameFails(str_repeat('a', 64)))->toBeTrue();
});

it('rejects reserved words', function () {
    expect(usernameFails('admin'))->toBeTrue()
        ->and(usernameFails('api'))->toBeTrue()
        ->and(usernameFails('klaroly'))->toBeTrue();
});

it('rejects any name in the username history, released or not', function () {
    $account = Account::factory()->create(['username' => 'firstname']);
    $account->update(['username' => 'secondname']);

    expect(UsernameHistory::where('username', 'firstname')->value('released_at'))->not->toBeNull()
        ->and(usernameFails('firstname'))->toBeTrue()
        ->and(usernameFails('secondname'))->toBeTrue()
        ->and(usernameFails('firstname', $account->id))->toBeFalse();
});

it('records the history when an account claims and changes its username', function () {
    $account = Account::factory()->create(['username' => 'Original']);

    expect($account->username)->toBe('original')
        ->and($account->usernameHistory()->count())->toBe(1);

    $account->update(['username' => 'renamed']);

    expect($account->usernameHistory()->count())->toBe(2)
        ->and($account->usernameHistory()->whereNull('released_at')->value('username'))->toBe('renamed');
});

it('gives each failure a translated message', function () {
    $validator = Validator::make(['username' => 'Nope'], ['username' => [new Username]]);

    expect($validator->errors()->first('username'))->toBe(__('validation.username.format', ['attribute' => 'username']));
});
