<?php

namespace App\Enums;

use App\Enums\Concerns\HasCheckConstraint;

/**
 * The feature toggles an account can switch on and off.
 *
 * Read only through App\Services\Features.
 */
enum FeatureKey: string
{
    use HasCheckConstraint;

    case Enquiries = 'enquiries';
    case Quotes = 'quotes';
    case Agreements = 'agreements';
    case Invoicing = 'invoicing';
    case Reminders = 'reminders';
    case Notes = 'notes';
    case ClientPages = 'client_pages';
    case Travel = 'travel';
}
