<?php

namespace App\Enums;

/**
 * Whose court the ball is in, which is what business logic 18.1 groups the
 * home screen's attention block by: "3 need you", then "2 waiting on clients".
 *
 * Computed and never stored, so it has no HasCheckConstraint, for the same
 * reason App\Enums\WaitingOn and App\Enums\EndingSide have none: there is no
 * column for a constraint to guard.
 *
 * **It is sent rather than left for the client to derive**, which is the rule
 * lost_side already follows on the enquiries row: the party is a fact about
 * the record and the heading beside it is wording, and facts come from the
 * server. The derivation looks free, because every WaitingOn value is spelled
 * artist_* or client_*, and that is exactly what makes it a trap: a client
 * doing value.startsWith('artist_') is parsing an identifier for meaning, and
 * it breaks silently the first time a value is named for the thing rather than
 * for the party.
 *
 * **It is not App\Enums\EndingSide, which happens to have the same two
 * values.** That one records who ended an enquiry. Sharing one enum because
 * the strings match is how a field comes to mean two things, which is the
 * failure this codebase has already paid for twice.
 */
enum WaitingParty: string
{
    /** The artist can act on this alone. */
    case Artist = 'artist';

    /** Nothing moves until the client does something. */
    case Client = 'client';
}
