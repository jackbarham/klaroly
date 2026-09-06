import { getYear } from 'date-fns'
import { datePlace, venueShort as shortVenue, type DatedPlace, type Translate } from '@/lib/eventLine'
import type { Contact, ContactBooking, OutstandingAmount } from '@/types/contacts'
import type { SortMode } from '@/lib/contactView'

// Everything the contacts list works out about a contact: what to call them,
// which booking to show, how to sort them, whether the filter matches and
// which pill they get.
//
// It is all plain functions taking plain values, so each rule can be tested on
// its own rather than through a mounted list, and so a component never carries
// a rule of its own. Nothing in here reads the store, the router or the clock:
// today is always passed in, the way the bookings screen passes it, so that
// every part of the screen agrees about what today is even across midnight.

// Re-exported rather than declared twice: it is the same narrow function type
// the shared line-writer takes, and two identical declarations are two things
// to widen the day one of them needs a second argument.
export type { Translate }

/**
 * A string with its accents removed and its case dropped, for comparing.
 *
 * NFD splits an accented character into the letter and the mark, and the
 * property escape then removes the marks, so "Brontë" becomes "bronte" and
 * somebody typing "bront" on a keyboard with no diaeresis still finds her.
 * It is used for the filter and for the A to Z initial, so a surname beginning
 * with Á files under A rather than into a group of its own at the end.
 */
export function fold(value: string): string {
  return value.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase()
}

export function digitsOf(value: string): string {
  return value.replace(/\D/g, '')
}

// A last name is nullable, so both halves are optional in practice and the
// result is trimmed rather than joined blindly.
export function fullName(contact: Contact): string {
  return [contact.first_name, contact.last_name].filter(Boolean).join(' ')
}

// One letter for somebody with one name, two for everybody else. Folded first,
// so an accented initial is a plain letter in the circle rather than a mark
// that reads as a smudge at 40px.
export function initials(contact: Contact): string {
  const parts = [contact.first_name, contact.last_name].filter((part): part is string => Boolean(part))

  return parts.map((part) => fold(part).charAt(0).toUpperCase()).join('')
}

// What the row is about: the next job if there is one, otherwise the last.
// Both arrive on the payload, so this never walks the bookings array and works
// on a list payload that has left the array out.
export function nearestBooking(contact: Contact): ContactBooking | null {
  return contact.next_booking ?? contact.last_booking
}

/**
 * A booking as the shared line-writer wants it.
 *
 * The only difference is the name of one field: this payload calls it
 * event_type and an enquiry's event calls it type, because each mirrors its own
 * endpoint. One adapter line here is the price of both screens shortening a
 * venue the same way.
 */
function asPlace(booking: ContactBooking): DatedPlace {
  return {
    type: booking.event_type,
    date: booking.date,
    venue_name: booking.venue_name,
    city: booking.city,
  }
}

export function venueShort(booking: ContactBooking): string | null {
  return shortVenue(asPlace(booking))
}

/**
 * The row's supporting line, which is always the nearest booking and never the
 * phone number.
 *
 * The shortening lives in src/lib/eventLine.ts, because the enquiries list
 * draws the same line from a different payload and three rules measured once
 * at 375px must not be written down twice. What stays here is which booking a
 * contact's row is about; what moved is how a date and a place are written.
 */
export function secondLine(booking: ContactBooking, today: Date, t: Translate): string {
  return datePlace(asPlace(booking), today, t)
}

// -- The filter -------------------------------------------------------------

/**
 * Whether a contact survives the filter box.
 *
 * It narrows rows already in memory and there is no request behind it, so
 * every rule here is one that can be answered instantly for two hundred rows.
 *
 * Two of them are worth reading twice. A number is compared with the
 * non-digits stripped from both sides, so "900461" finds "07700 900461", but
 * only once three digits have been typed: with fewer, every contact whose
 * number contains a "7" matches and the list appears not to filter at all.
 * And text is compared folded, so an unaccented query finds an accented name.
 */
export function matches(contact: Contact, query: string): boolean {
  const wanted = fold(query.trim())

  if (wanted === '') {
    return true
  }

  const booking = nearestBooking(contact)

  const haystacks = [
    fullName(contact),
    contact.email,
    contact.city,
    // The venue as the row actually shows it, so what is on the screen is what
    // is being searched.
    booking ? venueShort(booking) : null,
  ]

  if (haystacks.some((value) => value !== null && fold(value).includes(wanted))) {
    return true
  }

  const typed = digitsOf(query)

  return typed.length >= 3
    && contact.phone !== null
    && digitsOf(contact.phone).includes(typed)
}

// -- The pill ---------------------------------------------------------------

export type PillKind = 'overdue' | 'owes' | 'upcoming'

export interface ContactPill {
  kind: PillKind
  // Present on the two money pills and absent on Upcoming.
  amount?: OutstandingAmount
}

/**
 * The one pill a row is allowed, in precedence order.
 *
 * Overdue beats owing beats an upcoming booking, because that is the order the
 * artist would act in: money that is late, then money that is coming, then
 * work that is coming. Only one renders.
 *
 * Both money pills disappear when the amounts-owed setting is off, and the row
 * then falls through the list like any other: a contact who owes money and has
 * a confirmed booking next month shows Upcoming instead of nothing. That is
 * deliberate. Switching money off is a request to hide figures, not a request
 * to hide the diary, and a row that went blank would lose something true for
 * the sake of something that is no longer being shown anyway.
 */
export function pillFor(contact: Contact, showAmounts: boolean): ContactPill | null {
  if (showAmounts) {
    const owed = contact.outstanding.filter((amount) => amount.minor > 0)
    const overdue = owed.find((amount) => amount.overdue)

    if (overdue) {
      return { kind: 'overdue', amount: overdue }
    }

    if (owed.length > 0) {
      return { kind: 'owes', amount: owed[0] }
    }
  }

  if (contact.next_booking && contact.next_booking.stage === 'confirmed') {
    return { kind: 'upcoming' }
  }

  return null
}

// -- Sorting and grouping ---------------------------------------------------

export interface ContactGroup {
  key: string
  // A heading that is a word is a locale key; one that is a year or a letter
  // is the text itself, because there is no key for "2024" or for "H".
  labelKey: string | null
  labelText: string | null
  contacts: Contact[]
}

// The name the A to Z order runs on: the last name where there is one, the
// first name where there is not. Somebody with one name sorts among everybody
// else by that name rather than being pushed to the end of the alphabet.
function alphaKey(contact: Contact): string {
  return fold(contact.last_name ?? contact.first_name)
}

function compareNames(a: Contact, b: Contact): number {
  const byLast = alphaKey(a).localeCompare(alphaKey(b), 'en-GB')

  return byLast !== 0 ? byLast : fold(a.first_name).localeCompare(fold(b.first_name), 'en-GB')
}

function initial(value: string): string {
  return fold(value).charAt(0).toUpperCase()
}

/**
 * Recency: what is coming, then what has been.
 *
 * Upcoming runs ascending, because the top of that group is the next job and
 * that is the row the artist opened the screen for. Everything else runs
 * descending from the most recent, grouped by the year it happened in, because
 * "the wedding last autumn" is how somebody is remembered once they are past.
 *
 * A contact with nothing at all against them, which is a contact typed in by
 * hand and not yet enquired, has no date to file under and goes last under its
 * own heading rather than being dropped or given a year it does not have.
 */
function byRecency(contacts: Contact[], today: Date): ContactGroup[] {
  const upcoming: Contact[] = []
  const past: Contact[] = []
  const never: Contact[] = []

  for (const contact of contacts) {
    if (contact.next_booking) {
      upcoming.push(contact)
    } else if (contact.last_booking) {
      past.push(contact)
    } else {
      never.push(contact)
    }
  }

  upcoming.sort((a, b) => (a.next_booking?.date ?? '').localeCompare(b.next_booking?.date ?? ''))
  past.sort((a, b) => (b.last_booking?.date ?? '').localeCompare(a.last_booking?.date ?? ''))
  never.sort(compareNames)

  const groups: ContactGroup[] = []

  if (upcoming.length > 0) {
    groups.push({ key: 'upcoming', labelKey: 'contacts.group.upcoming', labelText: null, contacts: upcoming })
  }

  const thisYear = getYear(today)

  for (const contact of past) {
    // The date is 'YYYY-MM-DD', so the year is its first four characters. It
    // is read rather than parsed because a plain calendar date has no instant
    // to be wrong about.
    const year = Number((contact.last_booking?.date ?? '').slice(0, 4))
    const band = year === thisYear
      ? { key: 'this_year', labelKey: 'contacts.group.this_year', labelText: null }
      : year === thisYear - 1
        ? { key: 'last_year', labelKey: 'contacts.group.last_year', labelText: null }
        : { key: `year-${year}`, labelKey: null, labelText: String(year) }

    const last = groups[groups.length - 1]

    if (last && last.key === band.key) {
      last.contacts.push(contact)

      continue
    }

    groups.push({ ...band, contacts: [contact] })
  }

  if (never.length > 0) {
    groups.push({ key: 'none', labelKey: 'contacts.group.no_bookings', labelText: null, contacts: never })
  }

  return groups
}

/**
 * A to Z: by last name where there is one, headed by its initial.
 *
 * Everybody with only a first name goes into one final group rather than being
 * sprinkled through the letters under their first initial, because a list that
 * files Anna under A next to Adebayo is a list where half the As are surnames
 * and half are not, and neither half can be scanned. Inside that group they
 * are in order of their first name, so the group is still alphabetical.
 */
function byName(contacts: Contact[]): ContactGroup[] {
  const named = contacts.filter((contact) => contact.last_name !== null).sort(compareNames)
  const other = contacts.filter((contact) => contact.last_name === null)
    .sort((a, b) => fold(a.first_name).localeCompare(fold(b.first_name), 'en-GB'))

  const groups: ContactGroup[] = []

  for (const contact of named) {
    const letter = initial(contact.last_name ?? contact.first_name)
    const last = groups[groups.length - 1]

    if (last && last.labelText === letter) {
      last.contacts.push(contact)

      continue
    }

    groups.push({ key: `letter-${letter}`, labelKey: null, labelText: letter, contacts: [contact] })
  }

  if (other.length > 0) {
    groups.push({ key: 'other', labelKey: 'contacts.group.other', labelText: null, contacts: other })
  }

  return groups
}

export function groupContacts(contacts: Contact[], sort: SortMode, today: Date): ContactGroup[] {
  return sort === 'alpha' ? byName(contacts) : byRecency(contacts, today)
}
