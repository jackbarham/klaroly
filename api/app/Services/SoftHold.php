<?php

namespace App\Services;

use App\Enums\BookingStage;
use App\Enums\HoldClass;
use App\Support\CurrentAccount;
use Carbon\CarbonImmutable;

/**
 * The one place bookings.hold_expires_at is worked out.
 *
 * Business logic 5.1 says an enquiry at Possible starts holding its date softly
 * and 5.3 says converting turns that soft hold into a real one. Until this
 * existed nothing in the application wrote the column at all, so artist_not_held
 * — first in the waiting-on precedence, above money, because the date itself
 * can be lost — could only fire for rows a seeder set by hand.
 *
 * **A service called explicitly, not a model event**, which is the same answer
 * this codebase already gave to the same question. `last_touched_at` faced it
 * first: how do you make sure every writer that touches a booking does a thing?
 * The answer was Booking::touchActivity(), an explicit method plus a written
 * rule that every write path calls it. A hook here and a method there would be
 * two answers to one question, and a hook would have to defer to a caller that
 * set the column itself, which is a trick a reader has to know.
 *
 * **Every write path that changes a booking's stage calls this**, the way every
 * write path calls touchActivity(). A writer that does not is not a bug in
 * itself: it is a bug on the home screen, where a date goes on looking held
 * after nothing is holding it, or looks unheld the moment it is pencilled in.
 *
 * **The hold never releases itself.** Nothing here runs on a timer and nothing
 * moves a stage, clears a date or frees a Saturday when a hold lapses. Expiry
 * changes what the app says and never what the data is: business logic 5.2 is
 * explicit that the app warns and never blocks, and an artist who lost a date
 * because software decided a hold had lapsed has lost a wedding to a feature.
 * artist_not_held is a sentence on a screen, and that is the whole behaviour.
 */
class SoftHold
{
    public function __construct(private readonly CurrentAccount $account) {}

    /**
     * The hold a booking should carry once it has moved from one stage to
     * another, or null when the new stage holds nothing.
     *
     * **One rule with three outcomes**, expressed as a comparison of hold
     * classes rather than as a list of interesting transitions, so a stage
     * nobody thought about cannot fall through a gap:
     *
     *   the class went UP     start a hold, $on plus the account's hold_days
     *   the class is NONE     clear it, because nothing is held
     *   otherwise             leave it exactly as it is
     *
     * What falls out of that, and each of the three is a decision:
     *
     * **Possible to Quoted does not restart the clock.** Both hold softly (5.1),
     * so an artist who sends a quote has not re-pencilled the date.
     *
     * **Converting to Provisional does restart it.** 5.3 says the soft hold
     * becomes a real one, which is a change of kind, so a new clock is the
     * honest reading. Without it, converting a thirteen-day-old soft hold would
     * put "the date is not held" on the home screen the next morning, about a
     * booking the artist had just pencilled in. converted_at is written in the
     * same breath, so the two agree about when the firm hold began.
     *
     * **Undoing a conversion leaves the hold alone.** Provisional back to
     * Possible is a class that went down and is still holding, so it falls into
     * "otherwise". Clearing it would be false, because the record still holds
     * the date softly, and restarting it would let an artist extend a hold for
     * ever by converting and un-converting. It also keeps "reversible" meaning
     * what decision 2026-09-06.2006 says it means: the stage and converted_at
     * go back, and nothing else was ever changed to go back.
     *
     * Re-sending the same stage is a no-op, because the class did not move.
     *
     * @param  ?BookingStage  $from  the stage being left, or null for a booking that did not exist
     * @param  ?CarbonImmutable  $existing  what the booking holds now
     * @param  ?CarbonImmutable  $on  the day the transition happens, the account's today by default
     */
    public function forTransition(
        ?BookingStage $from,
        BookingStage $to,
        ?CarbonImmutable $existing = null,
        ?CarbonImmutable $on = null,
    ): ?CarbonImmutable {
        $toClass = $this->classOf($to);

        if ($toClass === HoldClass::None) {
            return null;
        }

        $fromClass = $from === null ? HoldClass::None : $this->classOf($from);

        if ($toClass->rank() <= $fromClass->rank()) {
            return $existing;
        }

        return $this->startingOn($on)->addDays($this->holdDays());
    }

    /**
     * How strongly each stage holds its date, business logic 5.1.
     *
     * **A match with no default, so the enum cannot grow without this being
     * answered.** That is the point of the class rather than a list of stages
     * that hold: a stage missing from a list falls silently into "leave the
     * hold alone", and a confirmed booking would then carry a hold date for
     * ever, duly expire, and be kept off the home screen only by the stage
     * guard in App\Services\WaitingOnResolver. Two answers to one question with
     * one of them silently wrong until something new asks is the shape this
     * codebase keeps paying for.
     *
     * **confirmed, completed, closed and cancelled hold nothing**, and that is
     * a rule rather than a technicality. A date that is signed and deposited is
     * not being held pending anything; the hold is what got it there. So the
     * column means precisely one thing, "this date is being held and nothing
     * has secured it yet", which is what the resolver reads it for.
     *
     * **Nothing writes those four stages today**, so this branch is unreachable
     * in the running application: PATCH /api/enquiries caps at
     * Booking::SETTABLE_STAGES and no other route writes a stage at all. It is
     * written now because business logic 4.4 makes confirmation a transition,
     * triggered when a signed agreement and a covered deposit both exist, so
     * whatever records a signature or a payment is what will move the stage,
     * and that writer calls this like every other one.
     */
    public function classOf(BookingStage $stage): HoldClass
    {
        return match ($stage) {
            BookingStage::New,
            BookingStage::InConversation,
            BookingStage::Lost => HoldClass::None,

            BookingStage::Possible,
            BookingStage::Quoted => HoldClass::Soft,

            BookingStage::Provisional => HoldClass::Firm,

            BookingStage::Confirmed,
            BookingStage::Completed,
            BookingStage::Closed,
            BookingStage::Cancelled => HoldClass::None,
        };
    }

    /**
     * The account's hold length, or the default when it has no settings row.
     */
    private function holdDays(): int
    {
        return (int) ($this->account->require()->settings?->hold_days ?? 14);
    }

    /**
     * The day the hold starts counting from.
     *
     * The artist's own day and not the application's. APP_TIMEZONE is UTC, so
     * today() is a UTC day, and for the last hour of a British summer evening
     * that is already tomorrow: a hold pencilled in on Tuesday night would run
     * from Wednesday and expire a day late. The same reason
     * App\Models\Invoice::isOverdue() takes a day rather than assuming one.
     *
     * A caller may pass one, which is what lets a seeder say a conversion
     * happened three weeks ago and get an honestly lapsed hold out of the same
     * code path a real conversion uses.
     */
    private function startingOn(?CarbonImmutable $on): CarbonImmutable
    {
        // startOfDay because a caller may pass an instant: the seeder says a
        // conversion happened at converted_at, which carries a time. The column
        // is a date, so a time component would be dropped on the way in anyway,
        // and normalising here means the value this returns is the value that
        // is stored.
        return ($on ?? CarbonImmutable::today($this->account->require()->timezone))->startOfDay();
    }
}
