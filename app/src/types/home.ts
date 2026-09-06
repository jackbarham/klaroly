// What GET /api/home returns: business logic section 18's three blocks in one
// payload, plus the meta the screen was computed against.
//
// snake_case, because that is what the API speaks and what types/auth.ts,
// types/bookings.ts, types/contacts.ts and types/enquiries.ts already mirror.
// Every name below is a column in docs/database-schema.md or a value section 8
// says is computed, so nothing here is invented on the way through.
//
// **One endpoint rather than three**, and the reason is not caching: the owed
// headline is the sum of the attention block's client_balance rows, so three
// routes would mean either the money one recomputing every booking's
// waiting-on state to produce one number, or this client summing the rows
// itself and holding the definition of a money figure. That Home most has to
// work with no signal (business logic 23.2) is the second reason.

import type { BookingStage, EventType, LocationType, WaitingOn } from '@/types/bookings'

/**
 * Whose court the ball is in, which is what business logic 18.1 groups the
 * attention block by.
 *
 * Sent by the API rather than parsed off the front of `waiting_on`, on the same
 * rule `lost_side` follows: the party is a fact about the record and the
 * heading beside it is wording. Reading it as `value.startsWith('artist_')`
 * would be parsing an identifier for meaning.
 */
export type WaitingParty = 'artist' | 'client'

/**
 * What the period figures count.
 *
 * 'payments' is cash received on the payment date, which is cash basis and
 * therefore a sole trader's tax return. 'booking_value' is what the weddings in
 * the period were worth, which is what an account with payment tracking off
 * gets instead. The block says which rather than leaving the screen to infer it
 * from an empty key, so it can never draw "Received: £0" on an account that
 * records no payments.
 */
export type MoneyBasis = 'payments' | 'booking_value'

export type PeriodKey = 'this_month' | 'three_months' | 'twelve_months' | 'business_year'

/**
 * One row of the attention block: a booking that is waiting on something.
 *
 * Flat, with every key always present and null where the value does not apply.
 * The API sends no copy at all: line one and line two are both built here from
 * these fields and the locale file, because a server that writes UI copy is a
 * server that has to be redeployed to fix a typo.
 */
export interface AttentionRow {
  booking_id: number
  waiting_on: WaitingOn
  party: WaitingParty
  client_name: string
  contact_id: number
  stage: BookingStage
  currency: string

  // Plain local calendar dates, 'YYYY-MM-DD', never a timestamp and never a
  // Date, so every timezone question stays on the server.
  event_date: string | null
  trial_date: string | null

  // UTC instants. **Never a day count.** Every "9 days late" and "sent 11 days
  // ago" is worked out at render with differenceInCalendarDays, for the reason
  // types/bookings.ts already gives: a number computed on the server is wrong
  // by the time a tab left open overnight reads it.
  last_touched_at: string
  created_at: string
  converted_at: string | null
  sent_at: string | null
  // The provisional hold. A date rather than an instant (schema 5.8).
  hold_expires_at: string | null

  // **The booking's money, not one invoice's.** Schema 5.15 allows a second
  // invoice to be raised manually, so these are summed across every invoice the
  // value is about: "£540 of £1,200 · 16 days late" is a true sentence about
  // the booking, and the days describe its oldest overdue invoice.
  //
  // outstanding_minor and due_on read against the BALANCE or against the
  // DEPOSIT, and waiting_on is the discriminator.
  outstanding_minor: number | null
  invoice_total_minor: number | null
  due_on: string | null
}

/**
 * One event in Next up, business logic 18.2.
 *
 * The unit is an event and not a booking, the same as GET /api/events: a trial
 * in March and the wedding in May are two rows, because a trial is a morning
 * out of the artist's diary exactly as the wedding is.
 */
export interface UpcomingEvent {
  event_id: number
  booking_id: number
  type: EventType
  label: string | null
  date: string
  // 'HH:mm', or null when no call time is agreed. Sent without a judgement
  // about whether it is early: an early start is a fact about a Saturday and
  // not a fault, which is why the prototype's warning-coloured 6:30 came out.
  start_time: string | null
  // Nullable, which is four render cases and not three (decision 228): the
  // venue columns cannot tell "nobody has said" from "at her own place, whose
  // address lives in settings".
  location_type: LocationType | null
  venue_name: string | null
  city: string | null
  client_name: string
  stage: BookingStage
  /**
   * **The whole BOOKING's party, not this event's**, and the difference is
   * visible rather than academic.
   *
   * party_members.event_id is nullable meaning "the main one" and nothing
   * counts per event today, so on a trial this says "Bride and 6" for an
   * appointment that is usually one person. Prompt 19 sent the booking-level
   * count and refused to paper over it, which was right, so the screen draws
   * the party on main events only until the party work fixes the count.
   */
  party_size: number | null
  // Seconds and metres, formatted at the edge like every other unit here.
  travel_duration_s: number | null
  travel_distance_m: number | null
}

export interface PeriodTotals {
  from: string
  to: string
  value_minor: number
  booking_count: number
  average_value_minor: number
}

export interface Outstanding {
  due_minor: number
  overdue_minor: number
  /**
   * **A subset of overdue_minor, never a third bucket.**
   *
   * due plus overdue is the whole of outstanding, and this names the part of
   * overdue that nobody is being chased for. A snoozed invoice is still late:
   * the snooze suppresses the chasing and not the fact. Never add the three,
   * and never draw them as three figures of equal standing.
   */
  snoozed_minor: number
}

export interface HomeMoney {
  currency: string
  basis: MoneyBasis
  // Whether work in another currency was left out of all of it. Schema section
  // 8 lets a money tile be filtered to the account currency rather than grouped
  // by it, and this is what stops that being a silent lie.
  excludes_other_currencies: boolean

  /**
   * Decision 27's headline: balances past their date on weddings that have
   * already happened. Null when invoicing is off, because nothing was ever
   * given a due date.
   *
   * **Not comparable with outstanding.overdue_minor**, which also counts unpaid
   * deposits: late money that is not a balance. They answer different questions
   * and must not be drawn as a figure and its total.
   */
  owed_minor: number | null
  owed_count: number | null
  /**
   * What a snooze took out of the headline above.
   *
   * A snoozed overdue balance leaves the attention list and takes its money
   * with it, which is correct, because the headline is the size of the problem
   * the artist has not already handled. What is not correct is that vanishing
   * silently, so the figure is named. Drawn only when it is above zero.
   */
  snoozed_minor: number | null

  outstanding: Outstanding | null

  // As of today, never governed by the period selector. Provisional is never
  // added to booked ahead: a held date is not money.
  booked_ahead_minor: number
  booked_ahead_count: number
  provisional_minor: number
  provisional_count: number

  // All four at once rather than one behind a parameter, so the selector works
  // with no signal.
  periods: Record<PeriodKey, PeriodTotals>
}

export interface HomeSummary {
  attention: AttentionRow[]
  upcoming: UpcomingEvent[]
  money: HomeMoney
}

export interface AttentionMeta {
  // The count BEFORE the cap, which is what "See all 8" says. A preview that
  // quietly showed four of eight would be the amounts-owed switch problem on
  // the screen where it would hurt most.
  total: number
  returned: number
  truncated: boolean
}

export interface HomeMeta {
  /**
   * The flags this response was computed against.
   *
   * **The money block reads these and never the auth store's opinion**, so the
   * figures and the reason a figure is missing come from one place.
   */
  features: Record<string, boolean>
  attention: AttentionMeta
  /**
   * The artist's own calendar day, and the zone it was worked out in.
   *
   * **Every day count on this screen is computed against this and not against
   * the device's clock.** The server has already decided what is overdue using
   * this day, so a phone in another timezone doing its own arithmetic would put
   * numbers on the screen that the same screen's figures disagree with.
   */
  today: string
  timezone: string
}

export interface Home {
  summary: HomeSummary
  meta: HomeMeta
}
