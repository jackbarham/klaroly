<?php

use App\Enums\MarketingConsentSource;

it('records a consent given from the settings screen', function () {
    $user = createOwner(['marketing_consent_at' => null, 'marketing_consent_source' => null]);

    $this->actingAs($user)
        ->putJson('/api/user/marketing-consent', ['consented' => true])
        ->assertOk()
        ->assertJsonPath('data.user.marketing_consent_at', fn ($value) => $value !== null);

    $user->refresh();

    expect($user->marketing_consent_at)->not->toBeNull()
        ->and($user->marketing_consent_source)->toBe(MarketingConsentSource::Settings);
});

it('withdraws a consent and keeps the source that recorded it', function () {
    $user = createOwner(['marketing_consent_at' => null, 'marketing_consent_source' => null]);

    $this->actingAs($user)
        ->putJson('/api/user/marketing-consent', ['consented' => true])
        ->assertOk();

    $this->actingAs($user)
        ->putJson('/api/user/marketing-consent', ['consented' => false])
        ->assertOk()
        ->assertJsonPath('data.user.marketing_consent_at', null);

    $user->refresh();

    // The timestamp is the consent; the source stays as the record of where
    // the consent that was withdrawn had been given.
    expect($user->marketing_consent_at)->toBeNull()
        ->and($user->marketing_consent_source)->toBe(MarketingConsentSource::Settings);
});

it('leaves a signup consent recorded as a signup consent when it is withdrawn', function () {
    $user = createOwner([
        'marketing_consent_at' => now(),
        'marketing_consent_source' => MarketingConsentSource::AppSignup,
    ]);

    $this->actingAs($user)
        ->putJson('/api/user/marketing-consent', ['consented' => false])
        ->assertOk();

    $user->refresh();

    expect($user->marketing_consent_at)->toBeNull()
        ->and($user->marketing_consent_source)->toBe(MarketingConsentSource::AppSignup);
});

it('answers with exactly what the me endpoint answers', function () {
    $user = createOwner();

    $updated = $this->actingAs($user)
        ->putJson('/api/user/marketing-consent', ['consented' => true])
        ->assertOk()
        ->json();

    $me = $this->actingAs($user)->getJson('/api/me')->assertOk()->json();

    expect($updated)->toBe($me);
});

it('requires the field, so a forgotten one is never a silent withdrawal', function () {
    $user = createOwner(['marketing_consent_at' => now(), 'marketing_consent_source' => MarketingConsentSource::AppSignup]);

    $this->actingAs($user)
        ->putJson('/api/user/marketing-consent', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['consented']);

    expect($user->fresh()->marketing_consent_at)->not->toBeNull();
});
