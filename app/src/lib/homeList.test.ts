import { describe, expect, it } from 'vitest'
import {
  attentionGroups,
  countdownKey,
  daysAgo,
  daysLate,
  detailLine,
  isLate,
  placeKey,
  sentenceKey,
  showsParty,
  travelMinutes,
} from '@/lib/homeList'
import type { AttentionRow, UpcomingEvent } from '@/types/home'

const today = new Date(2026, 8, 6)

function row(overrides: Partial<AttentionRow> = {}): AttentionRow {
  return {
    booking_id: 1,
    waiting_on: 'artist_price',
    party: 'artist',
    client_name: 'Rosie Duthie',
    contact_id: 1,
    stage: 'possible',
    currency: 'GBP',
    event_date: '2027-07-04',
    trial_date: null,
    last_touched_at: '2026-09-06T09:00:00.000000Z',
    created_at: '2026-09-06T09:00:00.000000Z',
    converted_at: null,
    sent_at: null,
    hold_expires_at: null,
    outstanding_minor: null,
    invoice_total_minor: null,
    due_on: null,
    ...overrides,
  }
}

function event(overrides: Partial<UpcomingEvent> = {}): UpcomingEvent {
  return {
    event_id: 1,
    booking_id: 1,
    type: 'main',
    label: null,
    date: '2026-09-12',
    start_time: '06:30',
    location_type: 'venue',
    venue_name: 'Penbury Manor, Hitchin',
    city: 'Hitchin',
    client_name: 'Nadia Kerrigan',
    stage: 'confirmed',
    party_size: 5,
    travel_duration_s: 2520,
    travel_distance_m: 41400,
    ...overrides,
  }
}

// A translate that returns the key, so a test asserts which key was chosen
// rather than what the locale file happens to say today.
//
// The two date patterns are the exception and have to resolve: they are read by
// date-fns rather than shown, and a key handed to format() is a run of
// unescaped letters. They are the real values from the locale file, which is
// also what makes a rename of either key fail here.
const patterns: Record<string, string> = {
  'contacts.format.day_month': 'd MMM',
  'contacts.format.day_month_year': 'd MMM yyyy',
}

const t = (key: string): string => patterns[key] ?? key

const money = (minor: number): string => `£${(minor / 100).toFixed(0)}`

describe('the attention preview', () => {
  /**
   * **The cut is made on the array's order and the grouping happens after it.**
   *
   * The fixtures are written grouped by party, which is how they read, and
   * slicing them in that order gives four of the artist's rows and no client
   * group at all, so an overdue balance can never reach the preview. That was a
   * real bug found by building the prototype, and this is the assertion that
   * would have caught it.
   */
  it('takes the first N of the response order, then groups', () => {
    const rows = [
      row({ booking_id: 1, waiting_on: 'artist_not_held', party: 'artist' }),
      row({ booking_id: 2, waiting_on: 'client_balance', party: 'client' }),
      row({ booking_id: 3, waiting_on: 'client_deposit', party: 'client' }),
      row({ booking_id: 4, waiting_on: 'artist_enquiry_cold', party: 'artist' }),
      row({ booking_id: 5, waiting_on: 'artist_price', party: 'artist' }),
      row({ booking_id: 6, waiting_on: 'client_signature', party: 'client' }),
    ]

    const groups = attentionGroups(rows, 4)

    // Two and two, from the first four of decision 217's order.
    expect(groups.map((group) => group.party)).toEqual(['artist', 'client'])
    expect(groups[0].rows.map((found) => found.booking_id)).toEqual([1, 4])
    expect(groups[1].rows.map((found) => found.booking_id)).toEqual([2, 3])
  })

  it('lets a client row into the preview when it outranks the artist rows below it', () => {
    const rows = [
      row({ booking_id: 1, waiting_on: 'client_balance', party: 'client' }),
      row({ booking_id: 2, waiting_on: 'artist_enquiry_cold', party: 'artist' }),
      row({ booking_id: 3, waiting_on: 'artist_price', party: 'artist' }),
      row({ booking_id: 4, waiting_on: 'artist_price', party: 'artist' }),
      row({ booking_id: 5, waiting_on: 'artist_price', party: 'artist' }),
    ]

    const shown = attentionGroups(rows, 4).flatMap((group) => group.rows.map((found) => found.booking_id))

    // Grouping first and cutting each group would have dropped it: the client
    // group has one row and the artist group has four.
    expect(shown).toContain(1)
  })

  it('draws every row and no cap when the limit is null', () => {
    const rows = [row({ booking_id: 1 }), row({ booking_id: 2 }), row({ booking_id: 3 })]

    expect(attentionGroups(rows, null)[0].rows).toHaveLength(3)
    expect(attentionGroups(rows, 2)[0].rows).toHaveLength(2)
  })

  it('leaves out a band with nothing in it', () => {
    const groups = attentionGroups([row({ party: 'artist' })], null)

    expect(groups).toHaveLength(1)
    expect(groups[0].party).toBe('artist')
  })
})

describe('the two lines', () => {
  it('names a sentence key per waiting-on value', () => {
    expect(sentenceKey(row({ waiting_on: 'artist_not_held' }), today))
      .toBe('home.attention.sentence.artist_not_held')
  })

  /**
   * **A deposit that is not paid is not necessarily overdue.** The resolver's
   * deposit branch fires as soon as one is uncovered, without waiting for a due
   * date, because until it is paid the date is not secured. Calling that
   * "overdue" on a deposit due next month is a sentence the data does not
   * support.
   */
  it('only calls a deposit overdue when it is actually late', () => {
    expect(sentenceKey(row({ waiting_on: 'client_deposit', due_on: '2026-09-02' }), today))
      .toBe('home.attention.sentence.client_deposit')

    expect(sentenceKey(row({ waiting_on: 'client_deposit', due_on: '2026-10-02' }), today))
      .toBe('home.attention.sentence.client_deposit_unpaid')
  })

  it('pluralises the day figure so nothing reads "0 days ago"', () => {
    const arrivedToday = detailLine(row({
      waiting_on: 'artist_price',
      created_at: '2026-09-06T09:00:00.000000Z',
    }), today, t, money)

    expect(arrivedToday?.count).toBe(0)
  })

  it('builds the not-held line from converted_at and the event date', () => {
    const line = detailLine(row({
      waiting_on: 'artist_not_held',
      converted_at: '2026-08-21T09:00:00.000000Z',
      event_date: '2026-10-17',
    }), today, t, money)

    expect(line?.key).toBe('home.attention.detail.not_held')
    expect(line?.values.days).toBe(16)
  })

  it('builds the balance line from the amount, the total and the due date', () => {
    const line = detailLine(row({
      waiting_on: 'client_balance',
      outstanding_minor: 34000,
      invoice_total_minor: 68000,
      due_on: '2026-08-28',
    }), today, t, money)

    expect(line?.key).toBe('home.attention.detail.balance')
    expect(line?.values.amount).toBe('£340')
    expect(line?.values.total).toBe('£680')
    expect(line?.values.days).toBe(9)
  })

  /**
   * **The venue is not on a money row.** It was, and it is what pushed five of
   * eight second lines over at 375px: an overdue balance is found by the name
   * and the amount, and the venue there was decoration.
   */
  it('never puts a venue on any detail line', () => {
    const values = ([
      'artist_not_held', 'artist_enquiry_cold', 'artist_price', 'artist_review',
      'client_balance', 'client_deposit', 'client_signature', 'client_form',
    ] as const).map((waiting_on) => detailLine(row({
      waiting_on,
      converted_at: '2026-08-21T09:00:00.000000Z',
      sent_at: '2026-08-26T09:00:00.000000Z',
      outstanding_minor: 34000,
      due_on: '2026-08-28',
    }), today, t, money))

    for (const line of values) {
      expect(Object.keys(line?.values ?? {})).not.toContain('venue')
    }
  })

  it('derives every day figure rather than reading one from the payload', () => {
    // The payload carries instants and dates; nothing on it is a count. If a
    // day count were ever added to the type this would still pass, so the
    // assertion is that the arithmetic happens here, against a today that is
    // passed in.
    const line = detailLine(row({
      waiting_on: 'artist_enquiry_cold',
      last_touched_at: '2026-08-15T20:57:18.000000Z',
    }), today, t, money)

    expect(line?.values.days).toBe(daysAgo('2026-08-15T20:57:18.000000Z', today))
    expect(line?.values.days).toBe(22)
  })
})

describe('what is coloured', () => {
  /**
   * The only thing on this screen that wears danger or warning is money that is
   * genuinely late. A coloured dot per row and a warning-coloured call time
   * were both drawn and removed, and both would be added back by somebody who
   * had not watched them fail.
   */
  it('is late only on an overdue money row', () => {
    expect(isLate(row({ waiting_on: 'client_balance', due_on: '2026-08-28' }), today)).toBe(true)
    expect(isLate(row({ waiting_on: 'client_deposit', due_on: '2026-09-02' }), today)).toBe(true)

    // Not late: due in the future, and not a money row at all.
    expect(isLate(row({ waiting_on: 'client_balance', due_on: '2026-09-20' }), today)).toBe(false)
    expect(isLate(row({ waiting_on: 'artist_not_held' }), today)).toBe(false)
    expect(isLate(row({ waiting_on: 'client_signature', sent_at: '2026-08-01T09:00:00Z' }), today)).toBe(false)
  })

  it('counts the days late from the due date', () => {
    expect(daysLate('2026-08-28', today)).toBe(9)
    expect(daysLate('2026-09-20', today)).toBe(0)
  })
})

describe('the countdown', () => {
  it('reads in words and widens as it gets further off', () => {
    expect(countdownKey(0).key).toBe('home.countdown.today')
    expect(countdownKey(1).key).toBe('home.countdown.tomorrow')
    expect(countdownKey(6)).toEqual({ key: 'home.countdown.days', count: 6 })
    expect(countdownKey(9).key).toBe('home.countdown.next_week')
    expect(countdownKey(21)).toEqual({ key: 'home.countdown.weeks', count: 3 })
    expect(countdownKey(90)).toEqual({ key: 'home.countdown.months', count: 3 })
  })
})

describe('the next up row', () => {
  /**
   * **Four cases and not three** (decision 228): a null location_type is
   * "nobody has said", which is a real state for an early enquiry rather than a
   * missing value.
   */
  it('has four place cases', () => {
    expect(placeKey(event({ location_type: 'base' })).key).toBe('home.next.place.base')
    expect(placeKey(event({ location_type: 'client' })).key).toBe('home.next.place.client')
    expect(placeKey(event({ location_type: 'venue' })).key).toBe('home.next.place.venue')
    expect(placeKey(event({ location_type: null, venue_name: null, city: null })).key)
      .toBe('home.next.place.unknown')
  })

  /**
   * The venue is shortened by the helper the two list screens already share
   * rather than a third copy: "The Old Corn Exchange, Saffron Walden" does not
   * fit at 375px, which is the finding the enquiries list reported at 380px.
   */
  it('drops the town after the comma from a venue name', () => {
    expect(placeKey(event({ venue_name: 'The Old Corn Exchange, Saffron Walden' })).venue)
      .toBe('The Old Corn Exchange')
  })

  /**
   * **The party is drawn on main events only**, and that is a data problem
   * rather than a layout choice: party_size is the whole booking's party,
   * because party_members.event_id is nullable meaning "the main one". On a
   * trial it would say "Bride and 6" for an appointment that is usually one
   * person, which is a visibly wrong number rather than a missing one.
   */
  it('draws the party on a wedding and never on a trial', () => {
    expect(showsParty(event({ type: 'main', party_size: 5 }))).toBe(true)
    expect(showsParty(event({ type: 'trial', party_size: 5 }))).toBe(false)
    expect(showsParty(event({ type: 'main', party_size: null }))).toBe(false)
  })

  it('reads travel in whole minutes, and null when there is none', () => {
    expect(travelMinutes(event({ travel_duration_s: 2520 }))).toBe(42)
    expect(travelMinutes(event({ travel_duration_s: null }))).toBeNull()
  })
})
