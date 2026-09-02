<?php

namespace App\Enums;

use App\Enums\Concerns\HasCheckConstraint;

/**
 * Where a booking sits between first enquiry and closed.
 *
 * Enquiries are new, in_conversation, possible and quoted. Bookings are
 * provisional, confirmed, completed, closed and cancelled. Lost is archived.
 */
enum BookingStage: string
{
    use HasCheckConstraint;

    case New = 'new';
    case InConversation = 'in_conversation';
    case Possible = 'possible';
    case Quoted = 'quoted';
    case Provisional = 'provisional';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Closed = 'closed';
    case Lost = 'lost';
    case Cancelled = 'cancelled';
}
