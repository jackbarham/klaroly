<?php

namespace App\Enums;

use App\Enums\Concerns\HasCheckConstraint;

/**
 * The full menu of message templates. The first build seeds a handful of them.
 */
enum TemplateKey: string
{
    use HasCheckConstraint;

    case EnquiryAcknowledgement = 'enquiry_acknowledgement';
    case Introduction = 'introduction';
    case IntroductionFollowUp = 'introduction_follow_up';
    case Quote = 'quote';
    case DetailsFormRequest = 'details_form_request';
    case DetailsFormReminder = 'details_form_reminder';
    case AgreementToSign = 'agreement_to_sign';
    case AgreementReminder = 'agreement_reminder';
    case BookingConfirmed = 'booking_confirmed';
    case InvoiceDepositRequest = 'invoice_deposit_request';
    case DepositReminder = 'deposit_reminder';
    case TrialReminder = 'trial_reminder';
    case TrialFollowUp = 'trial_follow_up';
    case BalanceDue = 'balance_due';
    case MainEventReminder = 'main_event_reminder';
    case ThankYou = 'thank_you';
    case FeedbackRequest = 'feedback_request';
    case GalleryRequest = 'gallery_request';
}
