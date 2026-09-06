import { differenceInCalendarDays, format, getYear, parseISO } from 'date-fns'
import { venueShort, type Translate } from '@/lib/eventLine'
import type { AttentionRow, UpcomingEvent, WaitingParty } from '@/types/home'

// Everything the home screen works out about a row: which band it falls in,
// what its two lines say, and how far off a date is.
//
// All plain functions taking plain values, so each rule can be tested on its
// own rather than through a mounted screen, and so a component never carries a
// rule of its own. Nothing here reads the store, the router or the clock:
// **today is always passed in**, and on this screen it is the ACCOUNT's day
// from meta.today rather than the device's. The server has already decided what
// is overdue using that day, so a phone in another timezone doing its own
// arithmetic would put numbers on the screen that the same screen's figures
// disagree with.

// -- Day counts -------------------------------------------------------------

/**
 * How many whole calendar days ago an instant was.
 *
 * Calendar days rather than elapsed hours, so something sent at eleven last
 * night reads as one day ago this morning rather than as nought until eleven.
 * The instant comes from the server and the day is worked out here, which is
 * why every timestamp in the payload is an instant: a number computed on the
 * server would be wrong by the time a tab left open overnight read it.
 */
export function daysAgo(instant: string, today: Date): number {
  return Math.max(differenceInCalendarDays(today, parseISO(instant)), 0)
}

/**
 * How many whole calendar days until a date, negative when it has passed.
 */
export function daysUntil(date: string, today: Date): number {
  return differenceInCalendarDays(parseISO(date), today)
}

/**
 * How late something is, in whole days, or nought when it is not late.
 */
export function daysLate(due: string, today: Date): number {
  return Math.max(differenceInCalendarDays(today, parseISO(due)), 0)
}

// -- The countdown pill -----------------------------------------------------

/**
 * How far off a date is, in words, because the arithmetic is the whole content
 * of the pill.
 *
 * A date would make the reader do the sum, and the sum is what the artist is
 * asking. The bands widen as they get further off because precision stops
 * mattering: the difference between six and seven days is worth having and the
 * difference between three and four months is not.
 *
 * It is the same shape as the enquiries list's agoKey and deliberately not the
 * same function: that one counts backwards from a timestamp and this one counts
 * forwards to a date, and the wordings share no string.
 */
export function countdownKey(days: number): { key: string, count: number } {
  if (days <= 0) {
    return { key: 'home.countdown.today', count: 0 }
  }

  if (days === 1) {
    return { key: 'home.countdown.tomorrow', count: 1 }
  }

  if (days < 7) {
    return { key: 'home.countdown.days', count: days }
  }

  if (days < 14) {
    return { key: 'home.countdown.next_week', count: 1 }
  }

  if (days < 60) {
    return { key: 'home.countdown.weeks', count: Math.round(days / 7) }
  }

  return { key: 'home.countdown.months', count: Math.round(days / 30) }
}

// -- The attention block ----------------------------------------------------

export interface AttentionGroup {
  party: WaitingParty
  labelKey: string
  rows: AttentionRow[]
}

/**
 * The rows to draw, capped, then grouped by whose court the ball is in.
 *
 * **The cut is made on the array's order and the grouping happens after it.**
 * The endpoint returns decision 217's precedence, so taking the first N takes
 * the N most urgent. Grouping first and cutting each group gives four of the
 * artist's own rows and no client group at all, so an overdue balance can never
 * reach the preview. That was a real bug found by building the prototype, and
 * the order of these two lines is the whole fix.
 */
export function attentionGroups(rows: AttentionRow[], limit: number | null): AttentionGroup[] {
  const shown = limit === null ? rows : rows.slice(0, limit)

  const groups: AttentionGroup[] = [
    { party: 'artist', labelKey: 'home.attention.need_you', rows: [] },
    { party: 'client', labelKey: 'home.attention.waiting_on_clients', rows: [] },
  ]

  for (const row of shown) {
    groups.find((group) => group.party === row.party)?.rows.push(row)
  }

  // A band with nothing in it is not drawn at all, which is what lets the
  // preview show two of one party and none of the other without an empty
  // heading explaining itself.
  return groups.filter((group) => group.rows.length > 0)
}

/**
 * Line one: the sentence.
 *
 * The API sends no copy, so every one of these is a key with the client's name
 * interpolated. Each is kept short enough that line one does not wrap at 375px,
 * which is about forty characters including the name.
 */
export function sentenceKey(row: AttentionRow, today: Date): string {
  // **A deposit that is not paid is not necessarily overdue.** The resolver's
  // deposit branch fires as soon as one is uncovered, without waiting for a due
  // date, because until it is paid the date is not secured (business logic
  // 4.4). Calling that "overdue" on a deposit due next month is a sentence the
  // data does not support, so there are two.
  if (row.waiting_on === 'client_deposit' && !isLate(row, today)) {
    return 'home.attention.sentence.client_deposit_unpaid'
  }

  return `home.attention.sentence.${row.waiting_on}`
}

export interface DetailLine {
  key: string
  values: Record<string, string | number>
  // The number the line pluralises on, where it has one. "Arrived 0 days ago"
  // is what a single form produces on the day something arrives, and it reads
  // like a bug rather than like today.
  count?: number
}

/**
 * Line two: the detail, as a key and the values it interpolates.
 *
 * Each value's line names different facts, which is why this is a switch rather
 * than one format: "how long it has been provisional" is converted_at, "how
 * many days late" is a due date, and "sent 11 days ago" is on the agreement.
 *
 * **The venue is not here**, on any row. It was on the money rows and it is
 * what pushed five of eight second lines over at 375px: an overdue balance is
 * found by the name and the amount, and the venue there was decoration.
 *
 * Every day figure is computed here from the instant or date the server sent,
 * against the account's today. None of them arrives as a number.
 */
export function detailLine(
  row: AttentionRow,
  today: Date,
  t: Translate,
  money: (minor: number, currency: string) => string,
): DetailLine | null {
  switch (row.waiting_on) {
    case 'artist_not_held':
      return row.converted_at === null ? null : {
        key: 'home.attention.detail.not_held',
        values: { days: daysAgo(row.converted_at, today), date: eventDay(row.event_date, today, t) },
        count: daysAgo(row.converted_at, today),
      }

    case 'artist_enquiry_cold':
      return {
        key: 'home.attention.detail.cold',
        values: { days: daysAgo(row.last_touched_at, today), date: eventDay(row.event_date, today, t) },
        count: daysAgo(row.last_touched_at, today),
      }

    case 'artist_price':
      return {
        key: 'home.attention.detail.price',
        values: { days: daysAgo(row.created_at, today), date: eventDay(row.event_date, today, t) },
        count: daysAgo(row.created_at, today),
      }

    case 'artist_review':
      return {
        key: 'home.attention.detail.review',
        values: { trial: eventDay(row.trial_date, today, t), date: eventDay(row.event_date, today, t) },
      }

    case 'client_balance':
      return row.outstanding_minor === null ? null : {
        key: 'home.attention.detail.balance',
        values: {
          amount: money(row.outstanding_minor, row.currency),
          total: money(row.invoice_total_minor ?? 0, row.currency),
          days: row.due_on === null ? 0 : daysLate(row.due_on, today),
        },
      }

    case 'client_deposit':
      return row.outstanding_minor === null ? null : {
        key: 'home.attention.detail.deposit',
        values: {
          amount: money(row.outstanding_minor, row.currency),
          days: row.due_on === null ? 0 : daysLate(row.due_on, today),
          date: eventDay(row.event_date, today, t),
        },
      }

    case 'client_signature':
    case 'client_form':
      return row.sent_at === null ? null : {
        key: `home.attention.detail.${row.waiting_on}`,
        values: { days: daysAgo(row.sent_at, today), date: eventDay(row.event_date, today, t) },
        count: daysAgo(row.sent_at, today),
      }
  }
}

/**
 * Whether the detail line's day figure is money that is genuinely late, which
 * is the only thing on this screen that wears a colour.
 *
 * Two things were drawn and removed for this rule and both would be added back
 * by somebody who had not watched them fail: a coloured dot per row, which the
 * band heading two lines above already says, and a 6:30 call time in warning
 * colour, which says "fault" about a fact. --warning-text reads red, so
 * anything wearing it claims a problem.
 */
export function isLate(row: AttentionRow, today: Date): boolean {
  if (row.waiting_on !== 'client_balance' && row.waiting_on !== 'client_deposit') {
    return false
  }

  return row.due_on !== null && daysLate(row.due_on, today) > 0
}

// -- The Next up block ------------------------------------------------------

/**
 * Where an event happens, in the fewest words that identify it.
 *
 * **Four cases and not three** (decision 228): null location_type is "nobody
 * has said", which is a real state for an early enquiry rather than a missing
 * value.
 *
 * The venue uses venueShort from src/lib/eventLine.ts rather than a third copy
 * of the same rule. "The Old Corn Exchange, Saffron Walden" does not fit at
 * 375px, which is the finding the enquiries list already reported at 380px, so
 * the town after the comma goes and the name that identifies the venue stays.
 *
 * A base location renders as "Your place" today, which is wrong for an artist
 * renting a chair in a salon. There is already a caller waiting for a name for
 * the base location; this is not the place to solve it and not the place to
 * invent a second way of saying it.
 */
export function placeKey(event: UpcomingEvent): { key: string, venue: string | null } {
  const venue = venueShort(event)

  if (event.location_type === 'base') {
    return { key: 'home.next.place.base', venue: null }
  }

  if (event.location_type === 'client') {
    return { key: 'home.next.place.client', venue }
  }

  if (venue !== null) {
    return { key: 'home.next.place.venue', venue }
  }

  return { key: 'home.next.place.unknown', venue: null }
}

/**
 * Whether to draw the party on this row.
 *
 * **Main events only, and this is a data problem rather than a layout choice.**
 * party_size is the whole booking's party, because party_members.event_id is
 * nullable meaning "the main one" and nothing counts per event today. On a
 * wedding that is right. On a trial it says "Bride and 6" for an appointment
 * that is usually one person, which is a visibly wrong number rather than a
 * missing one, so it is left off until the party work fixes the count.
 */
export function showsParty(event: UpcomingEvent): boolean {
  return event.type === 'main' && event.party_size !== null
}

/**
 * The travel estimate in whole minutes, or null.
 *
 * Seconds on the wire and minutes on the screen: nobody plans a Saturday to the
 * second. Null when the artist has travel estimates switched off, which the API
 * has already decided, so this never has to ask.
 */
export function travelMinutes(event: UpcomingEvent): number | null {
  return event.travel_duration_s === null ? null : Math.round(event.travel_duration_s / 60)
}

/**
 * The date column: the weekday, the day number and the month.
 */
export function dateParts(date: string, t: Translate): { weekday: string, day: string, month: string } {
  const parsed = parseISO(date)

  return {
    weekday: format(parsed, t('home.format.weekday')),
    day: format(parsed, 'd'),
    month: format(parsed, t('home.format.month')),
  }
}

/**
 * A date on an attention row's detail line: the day and month, plus the year
 * when it is not this one.
 *
 * The patterns are contacts' keys rather than a third pair spelled the same
 * way, for the reason eventLine.ts gives: two places to change "12 Sep" and one
 * place to forget.
 */
function eventDay(date: string | null, today: Date, t: Translate): string {
  if (date === null) {
    return ''
  }

  const parsed = parseISO(date)
  const sameYear = getYear(parsed) === getYear(today)

  return format(parsed, t(sameYear ? 'contacts.format.day_month' : 'contacts.format.day_month_year'))
}
