import { differenceInCalendarDays, parseISO } from 'date-fns'
import { datePlace, venueShort, type Translate } from '@/lib/eventLine'
import type { EnquirySort } from '@/lib/enquiryView'
import type { BookingStage } from '@/types/bookings'
import type { Clash, Enquiry } from '@/types/enquiries'

// Everything the enquiries list works out about an enquiry: how long ago it
// was touched, what its second line says, which band it falls in, whether the
// filter matches and what its clash line reads.
//
// It is all plain functions taking plain values, so each rule can be tested on
// its own rather than through a mounted list, and so a component never carries
// a rule of its own. Nothing here reads the store, the router or the clock:
// today is always passed in, the way the contacts and bookings screens pass
// it, so every part of the screen agrees about what today is even across
// midnight.

// The four stages a live enquiry can be at, in the order the pipeline runs.
// Lost is not one of them: it is the archive, and it sorts last in every order
// under its own heading.
export const liveStages: BookingStage[] = ['new', 'in_conversation', 'possible', 'quoted']

// -- Staleness --------------------------------------------------------------

/**
 * How many whole calendar days ago this was last touched.
 *
 * Calendar days rather than elapsed hours, so something touched at eleven last
 * night reads as "Yesterday" this morning rather than as "Today" until eleven.
 * The instant comes from the server and the day is worked out here, which is
 * why last_touched_at is sent as an instant: a number computed on the server
 * would be wrong by the time a tab left open overnight read it.
 */
export function daysSince(instant: string, today: Date): number {
  return Math.max(differenceInCalendarDays(today, parseISO(instant)), 0)
}

/**
 * The staleness figure, in words, because the arithmetic is the whole content
 * of the field.
 *
 * A date would make the reader do the sum, and the sum is what the artist is
 * actually asking: "how long have I left this". The bands widen as they get
 * older because precision stops mattering: the difference between eleven and
 * twelve days is worth having and the difference between six and seven weeks
 * is not.
 */
export function agoKey(days: number): { key: string, count: number } {
  if (days <= 0) {
    return { key: 'enquiries.ago.today', count: 0 }
  }

  if (days === 1) {
    return { key: 'enquiries.ago.yesterday', count: 1 }
  }

  if (days < 14) {
    return { key: 'enquiries.ago.days', count: days }
  }

  if (days < 60) {
    return { key: 'enquiries.ago.weeks', count: Math.round(days / 7) }
  }

  return { key: 'enquiries.ago.months', count: Math.round(days / 30) }
}

/**
 * Whether this enquiry has gone quiet.
 *
 * **It reads waiting_on and computes no threshold of its own.** The number of
 * days is an account setting resolved once, on the server, inside
 * App\Services\WaitingOnResolver, and it never reaches the client. That is the
 * point rather than an implementation detail: the home screen's attention
 * block asks the same resolver, so the two screens cannot disagree about which
 * enquiries have gone quiet, and changing the number changes both.
 */
export function isCold(enquiry: Enquiry): boolean {
  return enquiry.waiting_on === 'artist_enquiry_cold'
}

// -- The second line --------------------------------------------------------

/**
 * The date and the place, or "No date yet".
 *
 * **The absent date is a first-class value.** "Next summer, we have not booked
 * the venue yet" is normal and is one of the most winnable kinds of enquiry
 * there is, and a row that says nothing where a date goes reads as a bug in
 * the app rather than as a fact about the wedding.
 *
 * The shortening is src/lib/eventLine.ts, shared with the contacts row,
 * because the three rules were measured once at 375px and a second copy of
 * them is a second set of answers to one measurement.
 *
 * "and a trial" is appended rather than replacing the date, because the main
 * day is what the row is about and the trial is a second appointment on the
 * same conversation. It lands on the tightest line on the screen and is one of
 * the two lines that truncate at 375px, which is accepted: the alternative is
 * a row that is silent about a trial the artist has already pencilled in.
 */
export function whenAndWhere(enquiry: Enquiry, today: Date, t: Translate): string {
  if (enquiry.event === null) {
    return t('enquiries.row.no_date')
  }

  const line = datePlace(enquiry.event, today, t)

  return enquiry.has_trial && enquiry.event.type === 'main'
    ? `${line} · ${t('enquiries.row.and_a_trial')}`
    : line
}

// -- The clash line ---------------------------------------------------------

export interface ClashLine {
  key: string
  count: number
}

/**
 * What is already on this enquiry's date, as the shortest sentence that says
 * it.
 *
 * **It is not a warning and it prevents nothing.** A Saturday with four
 * enquiries on it is a Saturday where three of them will book somebody else
 * within a fortnight, and the one rung today is the one that converts.
 *
 * **The line appears on a row whose own stage holds no date.** An enquiry at
 * in_conversation carries no calendar mark, per strengthByStage in
 * src/lib/dayMarks.ts, and it still reports the confirmed booking and the two
 * possible enquiries sitting on its Saturday, because the line describes the
 * DATE rather than this record's contribution to it. That is a deliberate
 * departure from the calendar's rule and not an oversight.
 *
 * The wording is short because the long version does not fit. "Already booked,
 * and two others want this date" truncates on every frame including the 400px
 * column; these are the forms that fit, and the long version survives on the
 * detail where there is room for it.
 */
export function clashLine(clash: Clash | null): ClashLine | null {
  if (clash === null) {
    return null
  }

  const others = clash.others

  if (clash.confirmed > 0) {
    return others > 0
      ? { key: 'enquiries.clash.booked_and_others', count: others }
      : { key: 'enquiries.clash.booked', count: 0 }
  }

  if (clash.provisional > 0) {
    return others > 0
      ? { key: 'enquiries.clash.pencilled_and_others', count: others }
      : { key: 'enquiries.clash.pencilled', count: 0 }
  }

  return others > 0 ? { key: 'enquiries.clash.others', count: others } : null
}

// -- The filter -------------------------------------------------------------

function fold(value: string): string {
  return value.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase()
}

/**
 * Whether an enquiry survives the filter box.
 *
 * It narrows rows already in memory and there is no request behind it, so
 * there is no debounce, no spinner and no minimum length.
 *
 * The stage and the source are matched as well as the name and the place, and
 * both earn it: "quoted" typed into a list ordered by neglect is a genuinely
 * useful narrowing, and "voice" finds the enquiries that arrived as a voice
 * note even when the source line is switched off. That it also matches a venue
 * containing the word is correct behaviour for a filter rather than a search.
 *
 * Both are matched through their translated words rather than their keys, so
 * what is typed is what is on the screen, and "in conversation" works as well
 * as "conversation".
 */
export function matches(enquiry: Enquiry, query: string, t: Translate): boolean {
  const wanted = fold(query.trim())

  if (wanted === '') {
    return true
  }

  const haystacks = [
    enquiry.client_name,
    enquiry.event ? venueShort(enquiry.event) : null,
    enquiry.event?.city ?? null,
    t(`bookings.stage.${enquiry.stage}`),
    enquiry.source ? t(`enquiries.source.${enquiry.source}`) : null,
    enquiry.source_booking?.client_name ?? null,
  ]

  return haystacks.some((value) => value !== null && fold(value).includes(wanted))
}

// -- The three orders -------------------------------------------------------

export interface EnquiryGroup {
  key: string
  labelKey: string
  // Whether this band is the alarming one, so the heading can wear the warning
  // family. Only Gone quiet is, and only in the staleness order.
  cold: boolean
  enquiries: Enquiry[]
}

function push(groups: EnquiryGroup[], key: string, labelKey: string, enquiry: Enquiry, cold = false): void {
  const last = groups[groups.length - 1]

  if (last && last.key === key) {
    last.enquiries.push(enquiry)

    return
  }

  groups.push({ key, labelKey, cold, enquiries: [enquiry] })
}

// Oldest touched first, which is the whole point of the default order.
function byNeglect(a: Enquiry, b: Enquiry): number {
  return a.last_touched_at.localeCompare(b.last_touched_at)
}

/**
 * Staleness, with New pinned above it.
 *
 * A brand new enquiry has the freshest timestamp in the list and would sort to
 * the bottom, which is exactly backwards, because it is the one nobody has
 * looked at. So New is pinned above everything and ordered newest first, and
 * staleness orders the rest. Two rules instead of one, and the two-rule
 * version is what the artist actually means.
 *
 * Gone quiet comes from the server; the other two boundaries are fixed at two
 * and eight days and need no setting at all.
 */
function byStaleness(enquiries: Enquiry[], today: Date): EnquiryGroup[] {
  const fresh = enquiries.filter((enquiry) => enquiry.stage === 'new')
    .sort((a, b) => b.last_touched_at.localeCompare(a.last_touched_at))
  const rest = enquiries.filter((enquiry) => enquiry.stage !== 'new').sort(byNeglect)

  const groups: EnquiryGroup[] = []

  if (fresh.length > 0) {
    groups.push({ key: 'unseen', labelKey: 'enquiries.group.unseen', cold: false, enquiries: fresh })
  }

  for (const enquiry of rest) {
    if (isCold(enquiry)) {
      push(groups, 'cold', 'enquiries.group.cold', enquiry, true)

      continue
    }

    const days = daysSince(enquiry.last_touched_at, today)

    if (days >= 8) {
      push(groups, 'over_a_week', 'enquiries.group.over_a_week', enquiry)
    } else if (days >= 2) {
      push(groups, 'this_week', 'enquiries.group.this_week', enquiry)
    } else {
      push(groups, 'recent', 'enquiries.group.recent', enquiry)
    }
  }

  return groups
}

// The pipeline, oldest touched first inside each stage, so the row at the top
// of Quoted is the quote nobody has chased.
function byStage(enquiries: Enquiry[]): EnquiryGroup[] {
  const groups: EnquiryGroup[] = []

  for (const stage of liveStages) {
    const inStage = enquiries.filter((enquiry) => enquiry.stage === stage).sort(byNeglect)

    if (inStage.length > 0) {
      groups.push({
        key: `stage-${stage}`,
        // New's heading is the same words the staleness order uses, because it
        // is the same group: the ones nobody has looked at.
        labelKey: stage === 'new' ? 'enquiries.group.unseen' : `bookings.stage.${stage}`,
        cold: false,
        enquiries: inStage,
      })
    }
  }

  return groups
}

/**
 * The wedding date, soonest first.
 *
 * No date yet goes last rather than being dropped, because an enquiry without
 * one is a real and often promising enquiry, and inside it they are ordered by
 * neglect so the group is still useful rather than arbitrary.
 */
function byDate(enquiries: Enquiry[], today: Date): EnquiryGroup[] {
  const dated = enquiries.filter((enquiry) => enquiry.event !== null)
    .sort((a, b) => (a.event?.date ?? '').localeCompare(b.event?.date ?? ''))
  const undated = enquiries.filter((enquiry) => enquiry.event === null).sort(byNeglect)

  const groups: EnquiryGroup[] = []

  for (const enquiry of dated) {
    const away = differenceInCalendarDays(parseISO(enquiry.event?.date ?? ''), today)

    if (away <= 31) {
      push(groups, 'this_month', 'enquiries.group.this_month', enquiry)
    } else if (away <= 92) {
      push(groups, 'next_three', 'enquiries.group.next_three', enquiry)
    } else {
      push(groups, 'later', 'enquiries.group.later', enquiry)
    }
  }

  if (undated.length > 0) {
    groups.push({ key: 'no_date', labelKey: 'enquiries.group.no_date', cold: false, enquiries: undated })
  }

  return groups
}

/**
 * The list, in bands.
 *
 * Lost comes out before any of the three orders run and goes back on the end
 * as its own group. Left in, a lost enquiry would sort into Gone quiet and
 * read as work to do, which is the opposite of what it is. Its heading cannot
 * be "Closed": that stage is taken by done and paid, and "Not going ahead"
 * covers both endings while the pill on each row says which one it was.
 */
export function groupEnquiries(
  enquiries: Enquiry[],
  sort: EnquirySort,
  today: Date,
  showLost: boolean,
): EnquiryGroup[] {
  const live = enquiries.filter((enquiry) => enquiry.stage !== 'lost')

  const groups = sort === 'stage'
    ? byStage(live)
    : sort === 'date' ? byDate(live, today) : byStaleness(live, today)

  if (!showLost) {
    return groups
  }

  const lost = enquiries.filter((enquiry) => enquiry.stage === 'lost')
    .sort((a, b) => b.last_touched_at.localeCompare(a.last_touched_at))

  if (lost.length > 0) {
    groups.push({ key: 'lost', labelKey: 'enquiries.group.lost', cold: false, enquiries: lost })
  }

  return groups
}
