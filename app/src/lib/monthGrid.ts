import { computed, toValue, type MaybeRefOrGetter } from 'vue'
import {
  eachDayOfInterval,
  endOfMonth,
  endOfWeek,
  format,
  isSameMonth,
  startOfMonth,
  startOfWeek,
} from 'date-fns'

// The days a calendar draws, and how many rows it needs to draw them.
//
// Two rules run through the whole of this file, and both exist because the
// obvious shortcut is wrong twice a year:
//
//   1. A month is built by walking calendar dates with eachDayOfInterval,
//      never by adding 24 hours to the day before. On 29 March a day is 23
//      hours long and the addition skips a date; on 25 October it is 25 and
//      the addition repeats one.
//   2. A day's key comes from format(d, 'yyyy-MM-dd'), which reads the local
//      calendar date, never from toISOString(), which is UTC and would file an
//      evening event under the previous day for the eight months the clocks
//      are forward.
//
// Monday first, because this is en-GB.
const weekOptions = { weekStartsOn: 1 } as const

export interface GridDay {
  date: Date
  // 'YYYY-MM-DD' in local time. The marks map is keyed by this.
  key: string
  // The number in the cell.
  dayOfMonth: string
  // False for the days either side that fill the first and last rows out to
  // seven. In week mode nothing is outside, because there is no month to be
  // outside of.
  inMonth: boolean
}

export type GridMode = 'month' | 'week'

// The key for a date, which is the one way a date becomes a string anywhere in
// this feature.
export function dayKey(date: Date): string {
  return format(date, 'yyyy-MM-dd')
}

// The seven weekday headings, derived rather than written out, so they are
// Monday-first because weekOptions says so and not because a list was typed in
// that order.
export function weekdayHeadings(pattern: string): string[] {
  const reference = new Date()

  return eachDayOfInterval({
    start: startOfWeek(reference, weekOptions),
    end: endOfWeek(reference, weekOptions),
  }).map((day) => format(day, pattern))
}

// The days of the month `month` falls in, padded out to whole weeks.
//
// The interval ends at endOfWeek(endOfMonth(m)), so a month gets the four,
// five or six rows it actually needs and is never padded to a fixed 42 cells.
// On a 375px phone each row is 49px and the list below only has about 200px to
// begin with, so a row saved is most of another booking on screen.
export function monthDays(month: Date): GridDay[] {
  const start = startOfWeek(startOfMonth(month), weekOptions)
  const end = endOfWeek(endOfMonth(month), weekOptions)

  return eachDayOfInterval({ start, end }).map((date) => ({
    date,
    key: dayKey(date),
    dayOfMonth: format(date, 'd'),
    inMonth: isSameMonth(date, month),
  }))
}

// The seven days of the week `anchor` falls in.
export function weekDays(anchor: Date): GridDay[] {
  return eachDayOfInterval({
    start: startOfWeek(anchor, weekOptions),
    end: endOfWeek(anchor, weekOptions),
  }).map((date) => ({
    date,
    key: dayKey(date),
    dayOfMonth: format(date, 'd'),
    // A week strip is not showing a month, so no day in it is an outsider.
    inMonth: true,
  }))
}

export interface MonthGrid {
  days: GridDay[]
  weeks: GridDay[][]
  rowCount: number
}

// What a grid renders, for a month or for a single week.
//
// `anchor` only matters in week mode, where it is the day the strip is built
// around; in month mode the month alone decides everything.
export function useMonthGrid(
  month: MaybeRefOrGetter<Date>,
  mode: MaybeRefOrGetter<GridMode> = 'month',
  anchor: MaybeRefOrGetter<Date | null> = null,
) {
  const days = computed<GridDay[]>(() => {
    if (toValue(mode) === 'week') {
      return weekDays(toValue(anchor) ?? toValue(month))
    }

    return monthDays(toValue(month))
  })

  // The same days again as rows of seven, because the grid marks up a week as
  // a row and arrow-key navigation moves between rows.
  const weeks = computed<GridDay[][]>(() => {
    const rows: GridDay[][] = []

    for (let index = 0; index < days.value.length; index += 7) {
      rows.push(days.value.slice(index, index + 7))
    }

    return rows
  })

  const rowCount = computed(() => weeks.value.length)

  return { days, weeks, rowCount }
}
