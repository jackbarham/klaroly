<?php

namespace App\Enums;

use App\Enums\Concerns\HasCheckConstraint;

/**
 * The nine feature toggles an account can switch on and off (decision 78).
 *
 * Read only through App\Services\Features. A key that is absent from an
 * account's map is off, so registration writes the full default map from
 * config/features.php rather than relying on absence.
 */
enum FeatureKey: string
{
    use HasCheckConstraint;

    case Enquiries = 'enquiries';
    case IntakeForms = 'intake_forms';
    case Agreements = 'agreements';
    case Invoicing = 'invoicing';
    case PaymentTracking = 'payment_tracking';
    case Automation = 'automation';
    case TravelEstimates = 'travel_estimates';
    case Photos = 'photos';
    case FeedbackRequests = 'feedback_requests';
}
