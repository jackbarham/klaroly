<?php

namespace App\Enums;

use App\Enums\Concerns\HasCheckConstraint;

/**
 * How an enquiry arrived.
 */
enum BookingSource: string
{
    use HasCheckConstraint;

    case Manual = 'manual';
    case WebForm = 'web_form';
    case ForwardedEmail = 'forwarded_email';
    case VoiceNote = 'voice_note';
    case CapturedAtEvent = 'captured_at_event';
    case Other = 'other';
}
