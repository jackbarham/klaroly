<?php

namespace App\Enums;

use App\Enums\Concerns\HasCheckConstraint;

/**
 * Why an enquiry ended, and which side ended it.
 *
 * An enquiry ends two ways. The client goes elsewhere, or the artist turns the
 * work down: already booked that Saturday, too far to drive, not the kind of
 * job she wants. The second is common, and filing it as a loss is wrong in the
 * record and wrong in every figure derived from it.
 *
 * **It is a reason with a side, not a stage** (decision 2026-09-06.1512).
 * bookings.lost_reason already exists as varchar(40) and nullable, so the whole
 * distinction costs one enum. A tenth stage would have bought the same label
 * and charged for it in strengthByStage, in WaitingOnResolver, in both list
 * filters, in the stage check constraint and in every future test of whether a
 * record is still live. The two endings behave identically: both release the
 * date, both archive the record, and the only thing that differs is who
 * decided, which is exactly what a reason is for.
 *
 * Two values read "Another reason" on screen and that is not a duplication:
 * the side is the fact being recorded. NoReply is load bearing, because
 * silence is the most common ending of all, and without a value for it the
 * artist either leaves the enquiry in the list for ever or files it under
 * something untrue.
 *
 * **The column has no check constraint yet.** The enum enforces the values at
 * the application boundary, and the constraint goes in with the schema
 * rewrite rather than as an ALTER migration of its own. checkConstraintSql()
 * is here so that migration is generated from this list rather than written
 * out a second time.
 *
 * The labels are the front end's, not this file's. The API sends the key.
 */
enum LostReason: string
{
    use HasCheckConstraint;

    // The client ended it.
    case WentElsewhere = 'went_elsewhere';
    case TooExpensive = 'too_expensive';
    case WeddingOff = 'wedding_off';
    case NoReply = 'no_reply';
    case ClientOther = 'client_other';

    // The artist ended it.
    case AlreadyBooked = 'already_booked';
    case TooFar = 'too_far';
    case NotRightFit = 'not_right_fit';
    case ArtistOther = 'artist_other';

    /**
     * Which side ended the enquiry.
     *
     * A match with no default, so adding a tenth case is a fatal error here
     * rather than a value that quietly reports the wrong side.
     */
    public function side(): EndingSide
    {
        return match ($this) {
            self::WentElsewhere,
            self::TooExpensive,
            self::WeddingOff,
            self::NoReply,
            self::ClientOther => EndingSide::Client,

            self::AlreadyBooked,
            self::TooFar,
            self::NotRightFit,
            self::ArtistOther => EndingSide::Artist,
        };
    }
}
