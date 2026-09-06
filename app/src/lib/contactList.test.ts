import { describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { groupContacts, matches, pillFor, secondLine } from '@/lib/contactList'
import type { Contact, ContactBooking } from '@/types/contacts'

// The rules the contacts list runs on. Each one is a plain function taking
// plain values, so none of this mounts anything: what is being tested is the
// rule, not a component's ability to call it.
//
// The real i18n is used rather than a stub, so the assertions are against the
// wording in src/locales/en-GB.json. A test that passes against invented
// strings would still pass with the locale file empty.
const t = (key: string): string => i18n.global.t(key)

// A fixed today, so nothing in here depends on the day it is run.
const today = new Date(2026, 8, 6)

function booking(over: Partial<ContactBooking> = {}): ContactBooking {
  return {
    id: 1,
    event_type: 'main',
    date: '2026-09-12',
    venue_name: 'Ashgrove Manor',
    city: 'Hertford',
    stage: 'confirmed',
    total_minor: 96000,
    currency: 'GBP',
    ...over,
  }
}

function contact(over: Partial<Contact> = {}): Contact {
  const bookings = over.bookings ?? []

  return {
    id: 1,
    first_name: 'Imogen',
    last_name: 'Hartwell',
    email: 'imogen.hartwell@example.com',
    phone: '07700 900461',
    address_line_1: '14 Sallow Rise',
    address_line_2: null,
    city: 'Hertford',
    postcode: 'SG14 1QD',
    country: 'GB',
    bookings,
    booking_count: bookings.length,
    next_booking: null,
    last_booking: null,
    outstanding: [],
    ...over,
  }
}

describe('sorting by recency', () => {
  const upcomingSoon = contact({ id: 1, last_name: 'Zeller', next_booking: booking({ date: '2026-09-20' }) })
  const upcomingLater = contact({ id: 2, last_name: 'Adams', next_booking: booking({ date: '2026-11-02' }) })
  const past = contact({ id: 3, last_name: 'Brook', last_booking: booking({ date: '2026-03-01' }) })

  it('puts an upcoming booking above a past one', () => {
    const groups = groupContacts([past, upcomingSoon], 'recent', today)

    expect(groups[0].labelKey).toBe('contacts.group.upcoming')
    expect(groups[0].contacts.map((entry) => entry.id)).toEqual([1])
  })

  it('orders upcoming ascending, so the next job is at the top', () => {
    const groups = groupContacts([upcomingLater, upcomingSoon], 'recent', today)

    expect(groups[0].contacts.map((entry) => entry.id)).toEqual([1, 2])
  })

  it('groups what has been by the year it happened in, most recent first', () => {
    const lastYear = contact({ id: 4, last_booking: booking({ date: '2025-06-14' }) })
    const older = contact({ id: 5, last_booking: booking({ date: '2023-08-19' }) })

    const groups = groupContacts([older, lastYear, past], 'recent', today)

    expect(groups.map((group) => group.labelKey ?? group.labelText)).toEqual([
      'contacts.group.this_year',
      'contacts.group.last_year',
      '2023',
    ])
  })

  // Somebody typed in from a card at a wedding fair. There is no date to file
  // her under, and inventing one or dropping her are both worse than a group.
  it('gives somebody with nothing booked a group of their own, at the end', () => {
    const groups = groupContacts([contact({ id: 6 }), upcomingSoon], 'recent', today)

    expect(groups[groups.length - 1].labelKey).toBe('contacts.group.no_bookings')
  })
})

describe('sorting A to Z', () => {
  it('sorts on the last name where there is one', () => {
    const people = [
      contact({ id: 1, first_name: 'Aaron', last_name: 'Zeller' }),
      contact({ id: 2, first_name: 'Zoe', last_name: 'Adams' }),
    ]

    const groups = groupContacts(people, 'alpha', today)

    expect(groups.flatMap((group) => group.contacts).map((entry) => entry.id)).toEqual([2, 1])
  })

  it('sorts on the first name where there is no last name, among everyone else', () => {
    const people = [
      contact({ id: 1, first_name: 'Anouk', last_name: null }),
      contact({ id: 2, first_name: 'Delphine', last_name: null }),
    ]

    const groups = groupContacts(people, 'alpha', today)

    expect(groups[0].contacts.map((entry) => entry.id)).toEqual([1, 2])
  })

  it('puts everyone without a last name in a final Other group', () => {
    const people = [
      contact({ id: 1, first_name: 'Anouk', last_name: null }),
      contact({ id: 2, first_name: 'Zoe', last_name: 'Adams' }),
    ]

    const groups = groupContacts(people, 'alpha', today)

    expect(groups.map((group) => group.labelKey ?? group.labelText)).toEqual(['A', 'contacts.group.other'])
    expect(groups[1].contacts.map((entry) => entry.id)).toEqual([1])
  })

  // Paired with the assertion above, so neither can quietly stop being about
  // anything: Anouk has to be filed under her own letter when she does have a
  // surname, or "she goes in Other" is not telling us why.
  it('files somebody with the same first name under their surname when they have one', () => {
    const groups = groupContacts([contact({ id: 1, first_name: 'Anouk', last_name: 'Weaver' })], 'alpha', today)

    expect(groups.map((group) => group.labelKey ?? group.labelText)).toEqual(['W'])
  })

  it('files an accented surname under its plain letter', () => {
    const groups = groupContacts([contact({ id: 1, first_name: 'Charlotte', last_name: 'Brontë' })], 'alpha', today)

    expect(groups[0].labelText).toBe('B')
  })
})

describe('the filter', () => {
  const person = contact({
    first_name: 'Charlotte',
    last_name: 'Brontë',
    phone: '07700 900461',
    city: 'Halifax',
    next_booking: booking({ venue_name: 'Corvel Hall, Little Hadham' }),
  })

  // The number is stored as it is written, with a space in it, and matched
  // with the non-digits stripped from both sides. So the second half of it
  // finds her, and so does a run that straddles the space.
  it('matches a number stored with spaces once three digits are typed', () => {
    expect(matches(person, '900461')).toBe(true)
    expect(matches(person, '00 900')).toBe(true)
  })

  // Below three digits every contact whose number contains that run matches,
  // and the list appears not to filter at all. "077" is the opening of the
  // number and would match on the rule above, which is what makes this pair
  // about the length and not about the digits.
  it('does not match a number on fewer than three digits', () => {
    expect(matches(person, '07')).toBe(false)
    expect(matches(person, '077')).toBe(true)
  })

  it('matches an accented name from an unaccented query', () => {
    expect(matches(person, 'bront')).toBe(true)
  })

  it('matches anywhere in a name, not only at the start', () => {
    expect(matches(person, 'harlot')).toBe(true)
  })

  it('matches the venue that the row actually shows', () => {
    expect(matches(person, 'corvel')).toBe(true)
  })

  it('matches the town', () => {
    expect(matches(person, 'halifax')).toBe(true)
  })

  it('lets everybody through when nothing has been typed', () => {
    expect(matches(person, '   ')).toBe(true)
  })

  // The empty case is the one the screen has to survive, and it is a false
  // answer rather than a thrown one.
  it('says no rather than failing when nothing matches', () => {
    expect(matches(person, 'zzzz')).toBe(false)
    expect(groupContacts([person].filter((entry) => matches(entry, 'zzzz')), 'recent', today)).toEqual([])
  })
})

describe('the second line', () => {
  it('drops the event label for a main event', () => {
    const line = secondLine(booking({ event_type: 'main', date: '2026-09-12' }), today, t)

    expect(line).toBe('12 Sep · Ashgrove Manor')
  })

  it('keeps the event label for anything else', () => {
    const line = secondLine(booking({ event_type: 'trial', date: '2026-08-15', venue_name: null, city: 'Hertford' }), today, t)

    expect(line).toBe('Trial, 15 Aug · Hertford')
  })

  it('drops the year when it is this year', () => {
    expect(secondLine(booking({ date: '2026-09-12' }), today, t)).not.toContain('2026')
  })

  // Paired with the assertion above: a year has to appear when it is not this
  // one, or "the year is dropped" is passing against a format that never
  // carried a year in the first place.
  it('keeps the year when it is not this year', () => {
    expect(secondLine(booking({ date: '2024-09-12' }), today, t)).toContain('2024')
  })

  it('uses only the part of the venue before the first comma', () => {
    const line = secondLine(booking({ venue_name: 'Corvel Hall, Little Hadham, Hertfordshire' }), today, t)

    expect(line).toContain('Corvel Hall')
    expect(line).not.toContain('Hertfordshire')
  })

  it('falls back to the town when a venue has no name', () => {
    expect(secondLine(booking({ venue_name: null, city: 'Halifax' }), today, t)).toContain('Halifax')
  })
})

describe('the pill', () => {
  const overdue = { currency: 'GBP', minor: 28000, overdue: true }
  const owing = { currency: 'GBP', minor: 45000, overdue: false }
  const soon = booking({ stage: 'confirmed' })

  it('puts overdue above owing', () => {
    expect(pillFor(contact({ outstanding: [owing, overdue] }), true)?.kind).toBe('overdue')
  })

  it('puts owing above an upcoming booking', () => {
    expect(pillFor(contact({ outstanding: [owing], next_booking: soon }), true)?.kind).toBe('owes')
  })

  it('falls through to upcoming when nothing is owed', () => {
    expect(pillFor(contact({ next_booking: soon }), true)?.kind).toBe('upcoming')
  })

  it('shows nothing at all when there is nothing to say', () => {
    expect(pillFor(contact(), true)).toBeNull()
  })

  it('only counts a future booking that is actually confirmed', () => {
    expect(pillFor(contact({ next_booking: booking({ stage: 'possible' }) }), true)).toBeNull()
  })

  it('hides both money pills when amounts owed are switched off', () => {
    expect(pillFor(contact({ outstanding: [overdue] }), false)).toBeNull()
    expect(pillFor(contact({ outstanding: [owing] }), false)).toBeNull()
  })

  // Paired with the assertion above: with the setting on, the same two
  // contacts have to produce the two money pills, or "they disappear" is
  // passing against a rule that never produced them.
  it('shows both money pills when amounts owed are switched on', () => {
    expect(pillFor(contact({ outstanding: [overdue] }), true)?.kind).toBe('overdue')
    expect(pillFor(contact({ outstanding: [owing] }), true)?.kind).toBe('owes')
  })

  // The money setting hides money, and nothing else: somebody using this
  // screen in front of a client should not have every row change shape.
  it('still shows an upcoming booking when amounts owed are switched off', () => {
    expect(pillFor(contact({ outstanding: [owing], next_booking: soon }), false)?.kind).toBe('upcoming')
  })

  it('reports the amount in its own currency rather than summing across two', () => {
    const euros = { currency: 'EUR', minor: 50000, overdue: true }
    const pill = pillFor(contact({ outstanding: [owing, euros] }), true)

    expect(pill?.amount).toEqual(euros)
  })
})
