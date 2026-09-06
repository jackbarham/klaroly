<?php

namespace App\Enums;

/**
 * How strongly a booking at a given stage holds its date, per business logic
 * 5.1's third column.
 *
 * Computed and never stored, so it carries no HasCheckConstraint, for the same
 * reason App\Enums\WaitingOn and App\Enums\WaitingParty carry none: there is no
 * column for a constraint to guard.
 *
 * **It exists so that App\Services\SoftHold can be total over BookingStage.**
 * The rule about when a hold starts is "the class went up", and expressing that
 * as a comparison rather than as a list of interesting transitions is what
 * makes a stage nobody thought about impossible: a new case in BookingStage
 * fails to compile against the match in SoftHold until somebody answers the
 * question for it.
 */
enum HoldClass: string
{
    /** Nothing is held. The date is free for anybody. */
    case None = 'none';

    /** Business logic 5.1's soft hold: a count badge on the calendar. */
    case Soft = 'soft';

    /** Pencilled in properly: a ring on the calendar, and 5.3's "real" hold. */
    case Firm = 'firm';

    /**
     * Where this sits in the order, so "the class went up" is a comparison.
     */
    public function rank(): int
    {
        return match ($this) {
            self::None => 0,
            self::Soft => 1,
            self::Firm => 2,
        };
    }
}
