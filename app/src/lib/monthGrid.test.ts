import { describe, expect, it } from 'vitest'
import { dayKey, monthDays, weekdayHeadings, weekDays } from '@/lib/monthGrid'

// The grid's arithmetic, and in particular the two ways of building a month
// that look identical for ten months of the year and are wrong twice.

describe('monthDays', () => {
  it('starts every month on a Monday', () => {
    for (let month = 0; month < 12; month += 1) {
      const days = monthDays(new Date(2026, month, 1))

      expect(days[0].date.getDay()).toBe(1)
      expect(days[days.length - 1].date.getDay()).toBe(0)
    }
  })

  // Three named months, one of each row count, so the "fit the month" rule is
  // pinned rather than assumed. Padding every month to a fixed 42 cells would
  // pass a test that only checked one of these.
  it('gives February 2027 four rows, because it starts on a Monday', () => {
    expect(monthDays(new Date(2027, 1, 1))).toHaveLength(28)
  })

  it('gives September 2026 five rows', () => {
    expect(monthDays(new Date(2026, 8, 1))).toHaveLength(35)
  })

  it('gives November 2026 six rows', () => {
    expect(monthDays(new Date(2026, 10, 1))).toHaveLength(42)
  })

  it('flags the days either side as outside the month', () => {
    const days = monthDays(new Date(2026, 8, 1))

    // September 2026 starts on a Tuesday, so one day of August leads it.
    expect(days[0].key).toBe('2026-08-31')
    expect(days[0].inMonth).toBe(false)
    expect(days[1].key).toBe('2026-09-01')
    expect(days[1].inMonth).toBe(true)
    expect(days.filter((day) => day.inMonth)).toHaveLength(30)
  })
})

// The test that matters: the grid must be built by walking calendar dates,
// never by adding 24 hours to the day before.
//
// Where that bug actually bites is worth writing down, because the obvious
// version of this test cannot fail. Both British clock changes fall on a
// Sunday, and the weeks here start on Monday, so a change is always the last
// day of its week and no drift is ever visible inside one. And a naive build
// anchored at midnight survives the spring forward too, because 00:00 plus 24
// hours becomes 01:00 on the same date.
//
// It is the autumn change, across a whole month grid, that breaks: after 25
// October every midnight becomes 23:00 the previous day, so the grid repeats
// 25 October and drops 5 November off the end. Checked against a deliberate
// 24-hour implementation, only this case goes red, so this is the assertion
// carrying the weight and the rest document the intent.
function assertsWalksTheCalendar(days: { key: string, date: Date }[]): void {
  const keys = days.map((day) => day.key)

  expect(new Set(keys).size).toBe(keys.length)

  for (let index = 1; index < days.length; index += 1) {
    const previous = days[index - 1].date
    const expected = new Date(
      previous.getFullYear(),
      previous.getMonth(),
      previous.getDate() + 1,
    )

    expect(days[index].key).toBe(dayKey(expected))
  }
}

describe('a month grid containing a clock change', () => {
  it('does not repeat a day across the autumn change, 25 October 2026', () => {
    const days = monthDays(new Date(2026, 9, 1))

    assertsWalksTheCalendar(days)
    expect(days.filter((day) => day.key === '2026-10-25')).toHaveLength(1)
    // The grid runs 28 September to Sunday 1 November. A 24-hour build repeats
    // the 25th and so falls a day short, ending on 31 October.
    expect(days.map((day) => day.key)).toContain('2026-11-01')
  })

  it('does not lose a day across the spring change, 29 March 2026', () => {
    const days = monthDays(new Date(2026, 2, 1))

    assertsWalksTheCalendar(days)
    expect(days.filter((day) => day.key === '2026-03-29')).toHaveLength(1)
  })
})

describe('the weeks containing a clock change', () => {
  it.each([
    ['the spring forward, 29 March 2026', new Date(2026, 2, 29)],
    ['the autumn back, 25 October 2026', new Date(2026, 9, 25)],
  ])('walks seven distinct consecutive dates across %s', (unused, date) => {
    const days = weekDays(date)

    expect(days).toHaveLength(7)
    assertsWalksTheCalendar(days)
    expect(days.map((day) => day.key)).toContain(dayKey(date))
  })
})

describe('dayKey', () => {
  it('is the local calendar date, not a UTC one', () => {
    // Late enough in the day that a UTC conversion moves it to the 15th while
    // the clocks are forward, which is what toISOString would have done.
    expect(dayKey(new Date(2026, 5, 14, 23, 30))).toBe('2026-06-14')
    expect(dayKey(new Date(2026, 5, 14, 0, 30))).toBe('2026-06-14')
    // And the other way, in winter, where a UTC conversion moves an early
    // hour back a day in a negative-offset zone.
    expect(dayKey(new Date(2026, 0, 1, 0, 15))).toBe('2026-01-01')
  })
})

describe('weekdayHeadings', () => {
  it('is Monday first, because this is en-GB', () => {
    expect(weekdayHeadings('EEE')).toEqual(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'])
  })
})
