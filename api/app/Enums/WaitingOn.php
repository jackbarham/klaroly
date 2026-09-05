<?php

namespace App\Enums;

/**
 * Axis two of the booking lifecycle, business logic section 6: whose court
 * the ball is in.
 *
 * Every value names the party being waited on, because that is what decides
 * the wording and whether it is the artist's problem or the client's. It has
 * no HasCheckConstraint, and that is the point: this is computed and never
 * stored (schema section 8), so there is no column for a constraint to guard.
 * Storing it would create the second source of truth that drifts.
 *
 * Two of the eight cannot be reached yet. ClientForm and ArtistReview both
 * turn on the intake form, and intake_forms and intake_questions are section
 * 7.4 of the schema: designed, not migrated. They are declared here rather
 * than left out because this enum is the axis, the app's own WaitingOn type
 * already carries all eight, and an enum trimmed now would have to be widened
 * again, breaking the contract twice. App\Services\WaitingOnResolver has an
 * explicit branch for each, and a test asserts they are unreachable by design
 * rather than by accident.
 */
enum WaitingOn: string
{
    case ClientForm = 'client_form';
    case ArtistReview = 'artist_review';
    case ArtistPrice = 'artist_price';
    case ClientSignature = 'client_signature';
    case ClientDeposit = 'client_deposit';
    case ClientBalance = 'client_balance';
    case ArtistNotHeld = 'artist_not_held';
    case ArtistEnquiryCold = 'artist_enquiry_cold';
}
