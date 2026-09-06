import type { FeatureKey, FeatureMap } from '@/types/auth'
import type { BookingStage } from '@/types/bookings'

// Which sections of the booking screen a record has earned.
//
// **The detail is the booking screen, not a second layout.** Business logic
// 4.3 is one bookings table with a stage column, so an enquiry and a booking
// are one record and there is no enquiry detail to keep in step with a booking
// one. What this screen renders is 19.3's header and summary against a record
// where most of it is still empty.
//
// **A section appears when the stage makes it the next useful thing, and when
// it appears empty it carries the next action rather than a full stop.** An
// enquiry gets dates, party, notes and activity, and price from Possible
// onwards; the rest arrive the moment it converts, saying "no agreement yet,
// send one", which is discovery and a working control at once, arriving at the
// exact moment it is the right thing to do.
//
// Drawing all nine as a way of advertising the features was considered and
// rejected: it pushes the five-in-the-morning summary below the fold on the
// most-used screen in the product, and the feature rule below cancels the
// discovery argument anyway.

export interface EnquirySection {
  key: string
  // The stage at which this section becomes the next useful thing.
  due: BookingStage
  // The feature that has to be on for it to exist at all, or null for the ones
  // that are not a feature.
  feature: FeatureKey | null
}

// The stages in order, so "due at Possible" can be compared against "is at
// Quoted" without listing every pair.
const rank: Record<string, number> = {
  new: 0,
  in_conversation: 1,
  possible: 2,
  quoted: 3,
  provisional: 4,
  confirmed: 5,
  completed: 6,
  closed: 7,
  cancelled: 8,
}

export const sections: EnquirySection[] = [
  { key: 'dates', due: 'new', feature: null },
  { key: 'party', due: 'new', feature: null },
  { key: 'price', due: 'possible', feature: null },
  { key: 'payments', due: 'provisional', feature: 'invoicing' },
  { key: 'details', due: 'provisional', feature: 'intake_forms' },
  { key: 'agreement', due: 'provisional', feature: 'agreements' },
  { key: 'messages', due: 'provisional', feature: 'automation' },
  { key: 'photos', due: 'provisional', feature: 'photos' },
  { key: 'notes', due: 'new', feature: null },
  { key: 'activity', due: 'new', feature: null },
]

/**
 * The sections this record draws, in order.
 *
 * **The feature is checked before the stage, and that is not an optimisation.**
 * Business logic 21 and 6: the app only ever asks about things the artist has
 * switched on, so with invoicing off nothing is ever waiting on a deposit. A
 * section for a switched-off feature is never drawn at any stage, whatever the
 * stage rule says, and gating the other way round would draw one the moment a
 * record converted.
 *
 * A lost enquiry is ranked as though it were at Quoted: it has ended, and the
 * useful thing about it is everything it had reached rather than nothing.
 */
export function sectionsFor(stage: BookingStage, features: FeatureMap | null): EnquirySection[] {
  const at = rank[stage === 'lost' ? 'quoted' : stage] ?? 0

  return sections.filter((section) => {
    if (section.feature !== null && features?.[section.feature] !== true) {
      return false
    }

    return at >= rank[section.due]
  })
}
