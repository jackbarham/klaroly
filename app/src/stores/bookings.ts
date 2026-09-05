import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { endOfMonth, startOfMonth, subMonths } from 'date-fns'
import * as bookings from '@/lib/bookings'
import { dayKey } from '@/lib/monthGrid'
import type { BookingEvent } from '@/types/bookings'

// The events the bookings screen draws, and the months its jump sheet dots.
// Components read this store and never call the API themselves.

export type BookingsStatus = 'idle' | 'loading' | 'ready' | 'failed'
export type WindowStatus = 'idle' | 'loading' | 'failed'

// What is known to be loaded. `to` of null means "and everything after", which
// is what the first call fetches.
interface Range {
  from: string
  to: string | null
}

// Whether a range covers a span outright. Null ends are treated as infinity in
// the direction they point.
function covers(range: Range, from: string, to: string): boolean {
  return range.from <= from && (range.to === null || range.to >= to)
}

/**
 * The loaded ranges, kept sorted and merged so that overlapping or touching
 * spans become one.
 *
 * A list rather than a single span, and the reason is a real case rather than
 * tidiness: the first load covers this month onwards, the jump sheet advertises
 * every month the account has ever worked, and someone picking January 2020
 * would need a contiguous backfill of six and a half years. That is past the
 * API's span cap, so the request would be refused and the month the artist
 * asked for would never load, for a completely ordinary action. With a list, a
 * distant month fetches its own small window and the range simply never claims
 * to cover the gap in between.
 */
function mergeRanges(ranges: Range[]): Range[] {
  const sorted = [...ranges].sort((a, b) => a.from.localeCompare(b.from))
  const merged: Range[] = []

  for (const range of sorted) {
    const last = merged[merged.length - 1]

    // Only merge when they actually meet. Two spans with a gap between them
    // stay two, which is the whole point.
    if (last && (last.to === null || last.to >= range.from)) {
      last.to = last.to === null || range.to === null ? null : (last.to > range.to ? last.to : range.to)

      continue
    }

    merged.push({ ...range })
  }

  return merged
}

export const useBookingsStore = defineStore('bookings', () => {
  const events = ref<BookingEvent[]>([])
  const monthsWithWork = ref<ReadonlySet<string>>(new Set())
  const status = ref<BookingsStatus>('idle')
  const windowStatus = ref<WindowStatus>('idle')

  const loaded = ref<Range[]>([])

  // Sorted the way the API sorts, so a merged window lands in the right place
  // and the list never has to sort again.
  function replace(incoming: BookingEvent[]): void {
    const byId = new Map(events.value.map((event) => [event.id, event]))

    for (const event of incoming) {
      byId.set(event.id, event)
    }

    events.value = [...byId.values()].sort((a, b) => {
      if (a.date !== b.date) {
        return a.date.localeCompare(b.date)
      }

      // Nulls last, matching the API's `start_time asc nulls last`.
      if (a.start_time !== b.start_time) {
        if (a.start_time === null) {
          return 1
        }

        if (b.start_time === null) {
          return -1
        }

        return a.start_time.localeCompare(b.start_time)
      }

      return a.id - b.id
    })
  }

  function isLoaded(from: string, to: string): boolean {
    return loaded.value.some((range) => covers(range, from, to))
  }

  /**
   * The first load: everything from the start of this month forward, in one
   * request, which is all a normal session ever needs. The months summary
   * comes with it.
   *
   * `from` is sent rather than left to the API's default of today, and the
   * difference is the first thing on screen. The calendar opens on the current
   * month, which starts before today, so with the default a Saturday the
   * artist worked ten days ago would draw as empty. An empty Saturday in
   * September is a lie about her own diary rather than a gap in a feature. The
   * default is still right for every other caller; this one knows what it is
   * drawing and the default does not.
   *
   * It costs a handful of rows, keeps the one-call-and-never-again property,
   * and makes the recorded range honest about a span that was really fetched.
   *
   * Fetches once. Coming back to Bookings from another tab does not refetch,
   * and two components mounting together do not fetch twice.
   */
  async function load(): Promise<void> {
    if (status.value === 'loading' || status.value === 'ready') {
      return
    }

    status.value = 'loading'

    const from = dayKey(startOfMonth(new Date()))

    try {
      const [rows, months] = await Promise.all([
        bookings.events({ from }),
        bookings.eventMonths(),
      ])

      replace(rows)
      monthsWithWork.value = new Set(months)
      loaded.value = mergeRanges([{ from, to: null }])
      status.value = 'ready'
    } catch {
      status.value = 'failed'
    }
  }

  /**
   * Make sure the month on screen has been fetched.
   *
   * The guard lives here rather than in the caller because the scroll sync
   * changes the month as the artist scrolls the list, so this is called
   * constantly and must be free when there is nothing to do.
   *
   * A window is the month either side of the one asked for, so the swipe
   * track's neighbours are covered and stepping through months with the arrows
   * does not fetch on every press.
   */
  async function ensureMonthLoaded(month: Date): Promise<void> {
    // Coverage is asked about the month itself, never about the padded window
    // fetched below, and the difference is the whole guard. Padding reaches
    // back a month, so testing the padded span would put its start before the
    // loaded range, nothing would ever look loaded, and scrolling forward
    // would fire a request per month. The padding is a prefetch for the swipe
    // track, not part of what is being asked for.
    //
    // The current month needs no special case: the first load starts at the
    // first of it, so it is covered like any other month ahead.
    if (isLoaded(dayKey(startOfMonth(month)), dayKey(endOfMonth(month))) || windowStatus.value === 'loading') {
      return
    }

    const from = dayKey(startOfMonth(subMonths(month, 1)))
    const to = dayKey(endOfMonth(new Date(month.getFullYear(), month.getMonth() + 1, 1)))

    windowStatus.value = 'loading'

    try {
      const rows = await bookings.events({ from, to })

      // Merged, never assigned: a window overlapping what is already held must
      // not duplicate a row or drop one.
      replace(rows)
      loaded.value = mergeRanges([...loaded.value, { from, to }])
      windowStatus.value = 'idle'
    } catch {
      // What is already on screen stays. A month that failed to load is a
      // month with no marks, which is recoverable; an empty list is not.
      windowStatus.value = 'failed'
    }
  }

  // Something for the failed state to do. The first load and a window failure
  // are separate, so this retries whichever is actually broken.
  async function retry(month?: Date): Promise<void> {
    if (status.value === 'failed') {
      status.value = 'idle'

      await load()

      return
    }

    if (windowStatus.value === 'failed' && month) {
      windowStatus.value = 'idle'

      await ensureMonthLoaded(month)
    }
  }

  const hasFailed = computed(() => status.value === 'failed' || windowStatus.value === 'failed')

  return {
    events,
    monthsWithWork,
    status,
    windowStatus,
    hasFailed,
    loaded,
    load,
    ensureMonthLoaded,
    retry,
  }
})
