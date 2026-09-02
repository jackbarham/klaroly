<?php

use App\Enums\FeatureKey;

/*
 * The feature map written to account_settings.features when an account is
 * registered. Keys are App\Enums\FeatureKey values; a key absent from an
 * account's map is off (decision 78), which is why every key is listed here.
 *
 * This is the launch shape. When the settings toggles screen ships, the
 * defaults move to the business logic 21.2 shape (enquiries on, everything
 * else off) and the artist switches the rest on themselves, per decision 78.
 */
return [

    'defaults' => [
        FeatureKey::Enquiries->value => true,
        FeatureKey::IntakeForms->value => false,
        FeatureKey::Agreements->value => true,
        FeatureKey::Invoicing->value => true,
        FeatureKey::PaymentTracking->value => true,
        FeatureKey::Automation->value => false,
        FeatureKey::TravelEstimates->value => false,
        FeatureKey::Photos->value => false,
        FeatureKey::FeedbackRequests->value => false,
    ],

];
