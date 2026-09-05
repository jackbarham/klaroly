// TEMPORARY. This file is invented data standing in for the bookings API, and
// it goes when that API lands. At that point loadBookingEvents() becomes a
// request through src/lib/api.ts and nothing else in the feature changes: the
// store already calls it once and holds what it returns, and no component
// imports this file (src/lib/bookings.guards.test.ts enforces that).
//
// Every venue and every person here is made up. They end up in screenshots, so
// no real venue is named.

import { addDays, addMonths, startOfMonth } from 'date-fns'
import { dayKey } from '@/lib/monthGrid'
import type { BookingEvent, BookingStage, EventType, WaitingOn } from '@/types/bookings'

// The dates are built relative to today rather than written out, so the screen
// still has work on it a year from now. Structure is what matters here: a
// Saturday carrying a booking and three enquiries at once, a month with
// nothing in it, and something far enough out to need the month jump.
const today = new Date()

// The nth Saturday of the month `months` from now, which is where wedding work
// actually falls and what makes the busy-Saturday case reachable at any time
// of year.
function saturday(months: number, nth: number): Date {
  const first = startOfMonth(addMonths(today, months))
  // getDay is 6 on a Saturday, so this is how far the first one is from the 1st.
  const offset = (6 - first.getDay() + 7) % 7

  return addDays(first, offset + (nth - 1) * 7)
}

// last_touched_at is a UTC instant on the booking, so toISOString is exactly
// right here and this is the one place in the feature that calls it. The thing
// that method must never be used for is a calendar day key, which is what
// dayKey() and format(d, 'yyyy-MM-dd') are for; bookings.guards.test.ts bans
// it everywhere else and names this file as the exception.
function touched(daysAgo: number): string {
  return new Date(today.getTime() - daysAgo * 24 * 60 * 60 * 1000).toISOString()
}

interface Draft {
  bookingId: number
  type: EventType
  label?: string
  date: Date
  startTime: string | null
  venueName: string | null
  city: string | null
  clientName: string
  stage: BookingStage
  total: number
  waitingOn?: WaitingOn
  touchedDaysAgo: number
}

// The busy Saturday, which is the case the whole screen exists for: one
// confirmed booking and three live enquiries on the same day.
const busySaturday = saturday(0, 4)

const drafts: Draft[] = [
  // This month.
  {
    bookingId: 1, type: 'main', date: saturday(0, 1), startTime: '06:30',
    venueName: 'Thornleigh Hall', city: 'Marlow', clientName: 'Hannah Wallace',
    stage: 'confirmed', total: 78000, touchedDaysAgo: 9,
  },
  {
    bookingId: 2, type: 'trial', date: addDays(saturday(0, 1), 3), startTime: '10:00',
    venueName: null, city: 'Reading', clientName: 'Priya Raman',
    stage: 'confirmed', total: 6000, touchedDaysAgo: 5,
  },
  {
    bookingId: 2, type: 'main', date: saturday(0, 2), startTime: '07:00',
    venueName: 'Marlbrook Court', city: 'Guildford', clientName: 'Priya Raman',
    stage: 'confirmed', total: 94000, touchedDaysAgo: 5,
  },
  {
    bookingId: 3, type: 'main', date: saturday(0, 3), startTime: '06:45',
    venueName: 'Wraysbury Mill', city: 'Farnham', clientName: 'Sophie Ellis',
    stage: 'provisional', total: 68000, waitingOn: 'client_signature', touchedDaysAgo: 2,
  },
  {
    bookingId: 3, type: 'trial', label: 'Trial and hair', date: addDays(saturday(0, 3), 5),
    startTime: '11:00', venueName: null, city: 'Reading', clientName: 'Sophie Ellis',
    stage: 'provisional', total: 6000, waitingOn: 'client_signature', touchedDaysAgo: 2,
  },
  {
    bookingId: 4, type: 'main', date: busySaturday, startTime: '06:00',
    venueName: 'Ashcombe Barn', city: 'Ware', clientName: 'Amelia Trent',
    stage: 'confirmed', total: 112000, touchedDaysAgo: 14,
  },
  {
    bookingId: 5, type: 'main', date: busySaturday, startTime: null,
    venueName: null, city: null, clientName: 'Rosie Kerr',
    stage: 'possible', total: 0, touchedDaysAgo: 4,
  },
  {
    bookingId: 6, type: 'main', date: busySaturday, startTime: null,
    venueName: 'Pentreath House', city: 'Bath', clientName: 'Nadia Iqbal',
    stage: 'possible', total: 0, touchedDaysAgo: 12,
  },
  {
    bookingId: 7, type: 'main', date: busySaturday, startTime: null,
    venueName: null, city: 'Swansea', clientName: 'Charlotte Dean',
    stage: 'quoted', total: 54000, waitingOn: 'artist_enquiry_cold', touchedDaysAgo: 41,
  },

  // Next month.
  {
    bookingId: 8, type: 'main', date: saturday(1, 1), startTime: null,
    venueName: 'Calderstone Grange', city: 'Gloucester', clientName: 'Imogen Slade',
    stage: 'possible', total: 0, touchedDaysAgo: 2,
  },
  {
    bookingId: 9, type: 'main', date: saturday(1, 1), startTime: null,
    venueName: null, city: null, clientName: 'Farrah Nasser',
    stage: 'quoted', total: 61000, touchedDaysAgo: 9,
  },
  {
    bookingId: 10, type: 'main', date: saturday(1, 2), startTime: '07:15',
    venueName: 'Harrowfield Manor', city: 'Ludlow', clientName: 'Bea Lawson',
    stage: 'provisional', total: 86000, waitingOn: 'client_deposit', touchedDaysAgo: 6,
  },
  {
    bookingId: 11, type: 'main', date: saturday(1, 3), startTime: '06:30',
    venueName: 'The Salt Loft', city: 'Bath', clientName: 'Grace Muir',
    stage: 'confirmed', total: 99000, touchedDaysAgo: 21,
  },
  {
    bookingId: 11, type: 'trial', date: addDays(saturday(1, 3), 1), startTime: '13:00',
    venueName: null, city: 'Bath', clientName: 'Grace Muir',
    stage: 'confirmed', total: 7500, touchedDaysAgo: 21,
  },
  {
    bookingId: 12, type: 'main', date: saturday(1, 4), startTime: '08:00',
    venueName: 'Ivybridge Farm', city: 'Shrewsbury', clientName: 'Nina Achebe',
    stage: 'confirmed', total: 104000, touchedDaysAgo: 30,
  },

  // Two months out.
  {
    bookingId: 13, type: 'main', date: saturday(2, 1), startTime: '06:45',
    venueName: 'The Old Cordwainery', city: 'Swansea', clientName: 'Elin Roberts',
    stage: 'provisional', total: 72000, waitingOn: 'artist_not_held', touchedDaysAgo: 24,
  },
  {
    bookingId: 14, type: 'main', date: saturday(2, 3), startTime: '07:30',
    venueName: 'Thornleigh Hall', city: 'Marlow', clientName: 'Maddie Okonjo',
    stage: 'confirmed', total: 88000, touchedDaysAgo: 3,
  },

  // Three months out is deliberately empty, so the screen can be seen with a
  // month that has nothing in it.

  // Four months out. A cancelled booking, which releases the date and must
  // therefore carry no mark at all.
  {
    bookingId: 15, type: 'main', date: saturday(4, 2), startTime: '07:00',
    venueName: 'Marlbrook Court', city: 'Guildford', clientName: 'Jo Fenwick',
    stage: 'cancelled', total: 66000, touchedDaysAgo: 18,
  },
  {
    bookingId: 16, type: 'main', date: saturday(4, 3), startTime: null,
    venueName: null, city: 'Ware', clientName: 'Tilly Brookes',
    stage: 'new', total: 0, touchedDaysAgo: 1,
  },

  // Five months out.
  {
    bookingId: 17, type: 'main', date: saturday(5, 2), startTime: '06:15',
    venueName: 'Pentreath House', city: 'Bath', clientName: 'Aoife Sheridan',
    stage: 'provisional', total: 91000, waitingOn: 'client_signature', touchedDaysAgo: 7,
  },

  // About a year out, which is what the month jump is for.
  {
    bookingId: 18, type: 'main', date: saturday(12, 2), startTime: '06:30',
    venueName: 'Thornleigh Hall', city: 'Marlow', clientName: 'Esme Hartley',
    stage: 'confirmed', total: 118000, touchedDaysAgo: 11,
  },

  // Behind us. The unfiltered list is upcoming only, so these are reachable by
  // tapping their day: a completed booking, which was worked and keeps its
  // mark, and a lost enquiry, which never held the date and has none.
  {
    bookingId: 19, type: 'main', date: saturday(-1, 2), startTime: '06:30',
    venueName: 'Calderstone Grange', city: 'Gloucester', clientName: 'Freya Lindqvist',
    stage: 'completed', total: 84000, waitingOn: 'client_balance', touchedDaysAgo: 26,
  },
  {
    bookingId: 20, type: 'main', date: saturday(-1, 3), startTime: null,
    venueName: null, city: null, clientName: 'Martha Oyelaran',
    stage: 'lost', total: 0, touchedDaysAgo: 48,
  },
]

// One request's worth of events, in the shape the API will send. Sorted by
// date, because the API will be, and a list that relies on the order arriving
// correct should be given it here too.
export async function loadBookingEvents(): Promise<BookingEvent[]> {
  const events: BookingEvent[] = drafts.map((draft, index) => ({
    id: index + 1,
    bookingId: draft.bookingId,
    type: draft.type,
    label: draft.label ?? null,
    date: dayKey(draft.date),
    startTime: draft.startTime,
    venueName: draft.venueName,
    city: draft.city,
    clientName: draft.clientName,
    stage: draft.stage,
    totalMinor: draft.total,
    currency: 'GBP',
    waitingOn: draft.waitingOn ?? null,
    lastTouchedAt: touched(draft.touchedDaysAgo),
  }))

  return events.sort((a, b) => a.date.localeCompare(b.date))
}
