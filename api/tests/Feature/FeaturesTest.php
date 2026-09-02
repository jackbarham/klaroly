<?php

use App\Enums\FeatureKey;
use App\Models\Booking;
use App\Services\Features;

it('treats a key absent from both maps as off', function () {
    $account = actingForAccount(['features' => []]);

    expect(app(Features::class)->enabled($account, FeatureKey::Enquiries))->toBeFalse();
});

it('follows the account map', function () {
    $account = actingForAccount(['features' => [
        FeatureKey::Enquiries->value => true,
        FeatureKey::Photos->value => false,
    ]]);

    $features = app(Features::class);

    expect($features->enabled($account, FeatureKey::Enquiries))->toBeTrue()
        ->and($features->enabled($account, FeatureKey::Photos))->toBeFalse();
});

it('lets a booking override the account in both directions', function () {
    $account = actingForAccount(['features' => [
        FeatureKey::Enquiries->value => true,
        FeatureKey::Photos->value => false,
    ]]);

    $booking = Booking::factory()->create(['feature_overrides' => [
        FeatureKey::Enquiries->value => false,
        FeatureKey::Photos->value => true,
    ]]);

    $features = app(Features::class);

    expect($features->enabled($account, FeatureKey::Enquiries, $booking))->toBeFalse()
        ->and($features->enabled($account, FeatureKey::Photos, $booking))->toBeTrue();
});

it('writes every key into a new account\'s map', function () {
    expect(array_keys(config('features.defaults')))->toBe(FeatureKey::values());
});
