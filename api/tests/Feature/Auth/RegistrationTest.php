<?php

use App\Enums\AccountRole;
use App\Enums\MarketingConsentSource;
use App\Models\Account;
use App\Models\AccountSettings;
use App\Models\AccountUser;
use App\Models\User;
use App\Models\UsernameHistory;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

/**
 * @return array<string, mixed>
 */
function registration(array $overrides = []): array
{
    return $overrides + [
        'business_name' => 'Ellie Marsh Makeup',
        'name' => 'Ellie Marsh',
        'email' => 'ellie@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ];
}

it('creates the user, account, settings, membership and history in one go', function () {
    Notification::fake();

    $this->postJson('/register', registration())->assertCreated();

    $user = User::sole();
    $account = Account::sole();
    $settings = AccountSettings::withoutGlobalScope('account')->sole();
    $membership = AccountUser::withoutGlobalScope('account')->sole();

    expect($account->name)->toBe('Ellie Marsh Makeup')
        ->and($account->username)->toBe('elliemarshmakeup')
        ->and($account->country)->toBe('GB')
        ->and($account->currency)->toBe('GBP')
        ->and($account->timezone)->toBe('Europe/London')
        ->and($account->trial_ends_at->toDateString())->toBe(now()->addDays(config('billing.trial_days'))->toDateString())
        ->and($settings->account_id)->toBe($account->id)
        ->and($settings->features)->toEqualCanonicalizing(config('features.defaults'))
        ->and($settings->deposit_type->value)->toBe('percent')
        ->and($settings->deposit_percent)->toBe(25)
        ->and($membership->account_id)->toBe($account->id)
        ->and($membership->user_id)->toBe($user->id)
        ->and($membership->role)->toBe(AccountRole::Owner)
        ->and($membership->can_edit)->toBeTrue()
        ->and($membership->can_see_prices)->toBeTrue()
        ->and($membership->can_see_invoices)->toBeTrue()
        ->and($membership->can_see_contacts)->toBeTrue()
        ->and($membership->accepted_at)->not->toBeNull()
        ->and(UsernameHistory::where('account_id', $account->id)->count())->toBe(1)
        ->and($user->last_account_id)->toBe($account->id)
        ->and($user->uuid)->not->toBeNull()
        ->and($user->email_verified_at)->toBeNull();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('logs the new user in on the web', function () {
    $this->postJson('/register', registration())->assertCreated();

    $this->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('data.account.username', 'elliemarshmakeup')
        ->assertJsonPath('data.membership.role', 'owner');
});

it('appends a number when the derived username collides', function () {
    Account::factory()->create(['username' => 'elliemarshmakeup']);

    $this->postJson('/register', registration())->assertCreated();

    expect(Account::where('name', 'Ellie Marsh Makeup')->value('username'))->toBe('elliemarshmakeup2');
});

it('never derives a reserved word', function () {
    $this->postJson('/register', registration(['business_name' => 'Admin']))->assertCreated();

    expect(Account::sole()->username)->not->toBe('admin')
        ->and(Account::sole()->username)->toBe('admin2');
});

it('drops leading digits and punctuation when deriving a username', function () {
    $this->postJson('/register', registration(['business_name' => '2 Faced: Hair & Beauty']))->assertCreated();

    expect(Account::sole()->username)->toBe('facedhairbeauty');
});

it('accepts a username the person chose', function () {
    $this->postJson('/register', registration(['username' => 'elliedoesfaces']))->assertCreated();

    expect(Account::sole()->username)->toBe('elliedoesfaces');
});

it('leaves nothing behind when the username is rejected', function () {
    $this->postJson('/register', registration(['username' => 'Not Valid']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['username']);

    expect(User::count())->toBe(0)
        ->and(Account::count())->toBe(0)
        ->and(UsernameHistory::count())->toBe(0);
});

it('leaves nothing behind when the username is reserved', function () {
    $this->postJson('/register', registration(['username' => 'admin']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['username']);

    expect(User::count())->toBe(0)->and(Account::count())->toBe(0);
});

it('records marketing consent when it is given', function () {
    $this->postJson('/register', registration(['marketing_consent' => true]))->assertCreated();

    $user = User::sole();

    expect($user->marketing_consent_at)->not->toBeNull()
        ->and($user->marketing_consent_source)->toBe(MarketingConsentSource::AppSignup);
});

it('records no consent when it is refused or absent', function () {
    $this->postJson('/register', registration(['marketing_consent' => false]))->assertCreated();
    $this->postJson('/logout');
    $this->postJson('/register', registration(['email' => 'other@example.com', 'business_name' => 'Other Makeup']))->assertCreated();

    expect(User::whereNotNull('marketing_consent_at')->count())->toBe(0)
        ->and(User::whereNotNull('marketing_consent_source')->count())->toBe(0);
});

it('rejects a second registration with the same email in a different case', function () {
    $this->postJson('/register', registration())->assertCreated();
    $this->postJson('/logout');

    $this->postJson('/register', registration(['email' => 'ELLIE@example.com', 'business_name' => 'Other Makeup']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('enforces the password policy', function () {
    $this->postJson('/register', registration(['password' => 'short', 'password_confirmation' => 'short']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});
