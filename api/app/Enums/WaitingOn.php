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

    /**
     * Whose court the ball is in, which is what business logic 18.1 groups the
     * home screen's attention block by.
     *
     * A match rather than a check on the value's prefix. Every value happens to
     * be spelled with its party in front, and reading it that way would make
     * the identifier carry meaning, so the day a value is named for the thing
     * rather than for the party the grouping goes wrong with nothing to catch
     * it. Listing all eight costs nothing and a new case cannot be added
     * without answering the question.
     */
    public function party(): WaitingParty
    {
        return match ($this) {
            self::ArtistNotHeld,
            self::ArtistEnquiryCold,
            self::ArtistPrice,
            self::ArtistReview => WaitingParty::Artist,

            self::ClientBalance,
            self::ClientDeposit,
            self::ClientSignature,
            self::ClientForm => WaitingParty::Client,
        };
    }

    /**
     * Decision 217's precedence, most urgent first, which is the order
     * GET /api/home returns the attention block in.
     *
     * Two principles behind it: losing a date beats being owed money, and
     * anything the artist can act on alone beats waiting on a client.
     *
     * **It is a list here rather than in the home controller, and the order
     * being load bearing is why.** App\Services\WaitingOnResolver holds the
     * same order as its list of checks, because first match wins decides which
     * single value a booking reports; this decides how the rows are then sorted
     * against each other. The two are the same decision applied to one booking
     * and to many, and the home screen's cap is what makes the second one
     * matter: the phone previews four rows, so an order grouped by party rather
     * than by precedence puts four artist rows at the top and an overdue
     * balance can never reach the preview. That was a real bug, found by
     * building the prototype.
     *
     * @return array<int, self>
     */
    public static function precedence(): array
    {
        return [
            self::ArtistNotHeld,
            self::ClientBalance,
            self::ClientDeposit,
            self::ArtistEnquiryCold,
            self::ArtistPrice,
            self::ArtistReview,
            self::ClientSignature,
            self::ClientForm,
        ];
    }

    /**
     * Where this value sits in the order above.
     */
    public function rank(): int
    {
        return array_search($this, self::precedence(), true);
    }

    /**
     * Whether this value is about an invoice's deposit rather than its balance.
     *
     * The home screen's attention row sends one amount and one due date, read
     * against whichever part of the invoice the value names, and this is the
     * question it asks to know which. It is here rather than as a comparison in
     * the resource because two methods there would otherwise each hold their
     * own copy of it, and the day a third money value is added they would have
     * to be found together.
     */
    public function isAboutTheDeposit(): bool
    {
        return $this === self::ClientDeposit;
    }
}
