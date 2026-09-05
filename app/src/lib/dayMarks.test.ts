import { describe, expect, it } from 'vitest'
import { marksFor } from '@/lib/dayMarks'
import type { BookingEvent, BookingStage } from '@/types/bookings'

let nextId = 0

function event(date: string, stage: BookingStage): BookingEvent {
  nextId += 1

  return {
    id: nextId,
    bookingId: nextId,
    type: 'main',
    label: null,
    date,
    startTime: null,
    venueName: null,
    city: null,
    clientName: 'Test Client',
    stage,
    totalMinor: 0,
    currency: 'GBP',
    waitingOn: null,
    lastTouchedAt: '2026-09-01T09:00:00.000Z',
  }
}

describe('marksFor', () => {
  // The case the whole screen exists for, from business logic 19.1: one
  // confirmed booking and three live enquiries on the same Saturday have to be
  // visible at once, not one instead of the other.
  it('gives a day with one confirmed and three possible events both marks', () => {
    const marks = marksFor([
      event('2026-09-26', 'confirmed'),
      event('2026-09-26', 'possible'),
      event('2026-09-26', 'possible'),
      event('2026-09-26', 'possible'),
    ])

    const day = marks.get('2026-09-26')

    expect(day?.strength).toBe('confirmed')
    expect(day?.possible).toBe(3)
    expect(day?.confirmed).toBe(1)
  })

  it('counts quoted alongside possible, because both are the soft hold', () => {
    const marks = marksFor([
      event('2026-09-26', 'possible'),
      event('2026-09-26', 'quoted'),
    ])

    expect(marks.get('2026-09-26')?.possible).toBe(2)
    expect(marks.get('2026-09-26')?.strength).toBe('possible')
  })

  it('takes the strongest state present as the mark', () => {
    const marks = marksFor([
      event('2026-09-26', 'provisional'),
      event('2026-09-26', 'possible'),
    ])

    expect(marks.get('2026-09-26')?.strength).toBe('provisional')
  })

  // A mark answers "is this day spoken for", so a stage that never held the
  // date or has released it carries nothing.
  it.each<BookingStage>(['new', 'in_conversation', 'lost', 'cancelled'])(
    'produces no mark at all for a %s booking',
    (stage) => {
      expect(marksFor([event('2026-09-26', stage)]).has('2026-09-26')).toBe(false)
    },
  )

  // A day whose only marked event is cancelled must go back to looking free,
  // or it warns against a Saturday the artist can work.
  it('drops the day when a cancelled booking is all that is left on it', () => {
    const marks = marksFor([
      event('2026-09-26', 'cancelled'),
      event('2026-09-26', 'new'),
    ])

    expect(marks.has('2026-09-26')).toBe(false)
  })

  // Completed and closed happened, so a past Saturday that was worked should
  // not look empty.
  it.each<BookingStage>(['confirmed', 'completed', 'closed'])(
    'marks a %s booking as confirmed',
    (stage) => {
      expect(marksFor([event('2026-09-26', stage)])?.get('2026-09-26')?.strength).toBe('confirmed')
    },
  )

  it('keeps unmarked events on the day, so tapping it still lists them', () => {
    const marks = marksFor([
      event('2026-09-26', 'confirmed'),
      event('2026-09-26', 'cancelled'),
    ])

    expect(marks.get('2026-09-26')?.events).toHaveLength(2)
  })

  it('sorts a day strongest first', () => {
    const marks = marksFor([
      event('2026-09-26', 'possible'),
      event('2026-09-26', 'confirmed'),
      event('2026-09-26', 'provisional'),
    ])

    expect(marks.get('2026-09-26')?.events.map((one) => one.stage))
      .toEqual(['confirmed', 'provisional', 'possible'])
  })
})
