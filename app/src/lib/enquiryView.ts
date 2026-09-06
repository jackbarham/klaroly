import { boolean, oneOf, readSettings as read, writeSettings as write, type Checks } from '@/lib/viewSettings'

// The five things the enquiries view menu changes.
//
// The mechanism is src/lib/viewSettings.ts, shared with the contacts menu.
// None of the values is: nothing here sorts by recency or leads with a
// booking, and nothing there orders by staleness or hides an ending.
//
// Five is close to the limit for one menu. A sixth needs an argument.

/**
 * The three orders, and why there are three.
 *
 * Staleness is the default and it is the one that answers the screen's own
 * question: the top of the list is the people nobody has spoken to for weeks.
 * Stage reads as a pipeline and makes the one-tap stage change obvious. Date
 * is the weakest of the three, because the calendar already answers that
 * question properly, and it is here because "who is on that Saturday" is
 * sometimes what you have opened the screen for.
 */
export type EnquirySort = 'staleness' | 'stage' | 'date'

export interface EnquiryViewSettings {
  sort: EnquirySort
  // How the enquiry arrived, as a third line on the row. Off by default: it is
  // the least useful of the three lines on a screen where the second line is
  // already the tightest thing on it.
  showSource: boolean
  // The total on a quoted row. On, because a quoted enquiry with no figure on
  // it is a row that cannot be acted on.
  showTotals: boolean
  // The clash line. On by default, and that is most of the answer to the
  // objection against it: a control that hides a double-booked Saturday is a
  // control that gets left off, and the artist then has a calm list and two
  // weddings on the same morning.
  showClashes: boolean
  // The archive. Off, because it is history and the live list is the work.
  showLost: boolean
}

const storageKey = 'klaroly.enquiries.view'

export const defaultSettings: EnquiryViewSettings = {
  sort: 'staleness',
  showSource: false,
  showTotals: true,
  showClashes: true,
  showLost: false,
}

// One check per field, so a `sort` of "recent", written by somebody who copied
// the contacts key, cannot reach the sort function.
const checks: Checks<EnquiryViewSettings> = {
  sort: oneOf('staleness', 'stage', 'date'),
  showSource: boolean,
  showTotals: boolean,
  showClashes: boolean,
  showLost: boolean,
}

export function readSettings(): EnquiryViewSettings {
  return read(storageKey, defaultSettings, checks)
}

export function writeSettings(settings: EnquiryViewSettings): void {
  write(storageKey, settings)
}
