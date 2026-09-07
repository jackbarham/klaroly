import { differenceInCalendarDays, format, getYear, parseISO } from 'date-fns'
import type { EventType } from '@/types/bookings'

// The date-and-place line, which two list screens draw and which must not be
// drawn twice.
//
// Contacts shows it under a name and Enquiries shows it under a name, and both
// shorten it the same three ways for the same reason: at 375px, with a pill
// beside it, the tail of a long venue is what survives a truncation and the
// part that identifies it is what does not. The rules were measured once, on
// the contacts screen, and a second copy of them is a second set of answers to
// one measurement.

// The only translations this needs are event-type words and date patterns,
// both already keys. A narrow function type rather than vue-i18n's own, so a
// test can pass the real i18n's t and nothing here has to know about a
// composer.
export type Translate = (key: string) => string

/**
 * A dated place, which is all this file needs of an event.
 *
 * Deliberately narrower than either payload. `Contact`'s bookings carry an id,
 * a stage and a total; an enquiry's event carries a location type; neither is
 * anything to do with writing a line of text, and a function that took one of
 * the two whole types could only ever serve one of them.
 */
export interface DatedPlace {
  type: EventType
  // A plain local calendar date, 'YYYY-MM-DD', never a timestamp and never a
  // Date, so every timezone question stays on the server.
  date: string
  venue_name: string | null
  city: string | null
}

/**
 * The part of a venue name before the first comma, or the town.
 *
 * Venues are written down the way they answer the phone, which is often
 * "Ashgrove Manor, Little Hadham, Hertfordshire". All of that is one venue and
 * only the first part identifies it, so the rest is dropped rather than
 * truncated: at 375px, with a pill beside it, the tail is what would survive
 * and the name is what would not.
 */
export function venueShort(place: Pick<DatedPlace, 'venue_name' | 'city'>): string | null {
  if (place.venue_name) {
    return place.venue_name.split(',')[0].trim()
  }

  return place.city
}

/**
 * How many whole calendar days ago something was, or nought when it is still
 * to come. Takes an instant or a plain date, since both parse the same way.
 *
 * Calendar days rather than elapsed hours, so something touched at eleven last
 * night reads as one day ago this morning rather than as nought until eleven.
 * The instant comes from the server and the day is worked out here, which is
 * why every timestamp in a payload is an instant: a number computed on the
 * server would be wrong by the time a tab left open overnight read it. Three
 * screens count days and all three count them here.
 */
export function daysAgo(when: string, today: Date): number {
  return Math.max(differenceInCalendarDays(today, parseISO(when)), 0)
}

/**
 * A calendar date as "12 Sep", with the year added when it is not this one.
 *
 * The patterns are contacts' keys rather than neutral ones, because they are
 * the same two patterns everywhere a date is drawn, and a second pair spelled
 * the same way is two places to change "12 Sep" and one place to forget.
 */
export function dayMonth(date: string, today: Date, t: Translate): string {
  const parsed = parseISO(date)
  const sameYear = getYear(parsed) === getYear(today)

  return format(parsed, t(sameYear ? 'contacts.format.day_month' : 'contacts.format.day_month_year'))
}

/**
 * The date and the place, shortened.
 *
 * Three things are dropped, and each one is dropped because it is the thing
 * that would be cut off at 375px rather than because it is uninteresting: the
 * event label when the event is the main one, because a row is about a wedding
 * by default; the year when it is this year, because that is the year the
 * artist is working in; and everything after the first comma of the venue.
 *
 * So it reads "12 Sep · Ashgrove Manor", or "Trial, 15 Aug · Hertford".
 *
 * The middot is the same separator the bookings list uses for the same kind of
 * line. Three list screens in one app separating their meta with different
 * characters is the sort of drift that is invisible in a diff and obvious on a
 * phone.
 */
export function datePlace(place: DatedPlace, today: Date, t: Translate): string {
  const when = dayMonth(place.date, today, t)

  // The event-type words live under bookings.event_type.*, and they are reused
  // rather than copied: "Trial" spelled in two locale groups is two places to
  // change it and one place to forget.
  const head = place.type === 'main'
    ? when
    : `${t(`bookings.event_type.${place.type}`)}, ${when}`

  const venue = venueShort(place)

  return venue ? `${head} · ${venue}` : head
}
