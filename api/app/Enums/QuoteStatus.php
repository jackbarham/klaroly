<?php

namespace App\Enums;

use App\Enums\Concerns\HasCheckConstraint;

/**
 * The outcome of a quote once it has reached the client.
 */
enum QuoteStatus: string
{
    use HasCheckConstraint;

    case Sent = 'sent';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Expired = 'expired';
}
