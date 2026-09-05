import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { jsonResponse } from '@/lib/testHelpers'
import { useBookingsStore } from '@/stores/bookings'
import type { BookingEvent } from '@/types/bookings'

// fetch is mocked rather than src/lib/bookings.ts, so these run the real chain:
// the store, the data module and the api client, including the query string.
// Stubbing the module would leave the one thing most likely to be wrong, the
// URL, untested.

const today = new Date(2026, 8, 5)

let nextId = 0

function event(date: string, over: Partial<BookingEvent> = {}): BookingEvent {
  nextId += 1

  return {
    id: nextId,
    booking_id: nextId,
    type: 'main',
    label: null,
    date,
    start_time: '06:30',
    venue_name: null,
    city: 'Bath',
    client_name: 'Test Client',
    stage: 'confirmed',
    total_minor: 78000,
    currency: 'GBP',
    waiting_on: null,
    last_touched_at: '2026-09-01T09:00:00.000000Z',
    ...over,
  }
}

let calls: string[] = []

/**
 * Answer each request from a map of path to body. A path nobody planned for
 * rejects, so an unexpected request fails a test rather than quietly
 * resolving to undefined.
 */
function serve(routes: Record<string, unknown>, failing: string[] = []): void {
  globalThis.fetch = vi.fn((url: string) => {
    const path = url.replace('http://api.test', '')

    calls.push(path)

    if (failing.some((fragment) => path.startsWith(fragment))) {
      return Promise.resolve(jsonResponse(500, { message: 'Server error' }))
    }

    // Exact path first, then '/api/events?*' for any windowed request, so a
    // test does not have to predict the window the store will ask for. A path
    // nobody planned for is a 404 rather than an empty success, so an
    // unexpected request fails the test rather than passing quietly.
    const match = Object.keys(routes).find((route) => path === route)
      ?? (path.startsWith('/api/events?') && '/api/events?*' in routes ? '/api/events?*' : undefined)

    if (match === undefined) {
      return Promise.resolve(jsonResponse(404, { message: `No route for ${path}` }))
    }

    return Promise.resolve(jsonResponse(200, { data: routes[match] }))
  }) as unknown as typeof fetch
}

function eventCalls(): string[] {
  return calls.filter((path) => path.startsWith('/api/events?') || path === '/api/events')
}

beforeEach(() => {
  setActivePinia(createPinia())
  calls = []
  vi.useFakeTimers({ toFake: ['Date'] })
  vi.setSystemTime(today)
})

afterEach(() => {
  vi.useRealTimers()
})

describe('the first load', () => {
  it('asks from the first of this month, not from today', async () => {
    serve({ '/api/events?*': [event('2026-09-26')], '/api/events/months': ['2026-09'] })

    const store = useBookingsStore()

    await store.load()

    // One request, and it starts at the first of the month rather than
    // leaving the API to default to today. The calendar opens on the current
    // month, which starts before today, and a Saturday already worked has to
    // draw as worked rather than as free.
    expect(eventCalls()).toEqual(['/api/events?from=2026-09-01'])
    expect(store.events).toHaveLength(1)
    expect(store.status).toBe('ready')
  })

  it('leaves no gap before today in the current month', async () => {
    serve({ '/api/events?*': [event('2026-09-01')], '/api/events/months': ['2026-09'] })

    const store = useBookingsStore()

    await store.load()
    calls = []

    // Today is the 5th. The whole month is held, so drawing it asks for
    // nothing more.
    await store.ensureMonthLoaded(new Date(2026, 8, 1))

    expect(eventCalls()).toEqual([])
    expect(store.events[0].date).toBe('2026-09-01')
  })

  it('fetches the months summary alongside it', async () => {
    serve({ '/api/events?*': [], '/api/events/months': ['2026-09', '2027-06'] })

    const store = useBookingsStore()

    await store.load()

    expect(calls).toContain('/api/events/months')
    expect([...store.monthsWithWork]).toEqual(['2026-09', '2027-06'])
  })

  it('does not fetch twice when two components mount together', async () => {
    serve({ '/api/events?*': [], '/api/events/months': [] })

    const store = useBookingsStore()

    await Promise.all([store.load(), store.load()])
    await store.load()

    expect(eventCalls()).toHaveLength(1)
  })
})

describe('windows', () => {
  async function ready(): Promise<ReturnType<typeof useBookingsStore>> {
    serve({
      '/api/events?*': [event('2026-09-26'), event('2026-10-17')],
      '/api/events/months': ['2026-09', '2026-10', '2020-01'],
    })

    const store = useBookingsStore()

    await store.load()
    calls = []

    return store
  }

  it('fires nothing for a month already inside the loaded range', async () => {
    const store = await ready()

    // The scroll sync changes the month as the artist scrolls, so this is
    // called constantly. Every one of these is forward of today and therefore
    // already held.
    await store.ensureMonthLoaded(new Date(2026, 9, 1))
    await store.ensureMonthLoaded(new Date(2026, 10, 1))
    await store.ensureMonthLoaded(new Date(2027, 5, 1))

    expect(eventCalls()).toEqual([])
  })

  it('fires exactly one request for a month outside it, and none on the way back', async () => {
    const store = await ready()

    await store.ensureMonthLoaded(new Date(2026, 5, 1))

    expect(eventCalls()).toHaveLength(1)
    expect(eventCalls()[0]).toContain('from=2026-05-01')
    expect(eventCalls()[0]).toContain('to=2026-07-31')

    calls = []

    // Back inside the original range, and back to the window just fetched.
    await store.ensureMonthLoaded(new Date(2026, 9, 1))
    await store.ensureMonthLoaded(new Date(2026, 5, 1))

    expect(eventCalls()).toEqual([])
  })

  // The case the interval list exists for. A contiguous backfill from today to
  // January 2020 is past the API's span cap, so it would be refused and the
  // month the jump sheet advertised would never load.
  it('fetches a distant month as its own small window rather than backfilling to it', async () => {
    const store = await ready()

    await store.ensureMonthLoaded(new Date(2020, 0, 1))

    expect(eventCalls()).toHaveLength(1)
    expect(eventCalls()[0]).toContain('from=2019-12-01')
    expect(eventCalls()[0]).toContain('to=2020-02-29')

    calls = []

    // The gap between that window and today is still not loaded, and the range
    // must not claim otherwise.
    await store.ensureMonthLoaded(new Date(2023, 0, 1))

    expect(eventCalls()).toHaveLength(1)
  })

  it('merges an overlapping window without duplicating or dropping rows', async () => {
    serve({
      '/api/events?*': [event('2026-09-26'), event('2026-10-17')],
      '/api/events/months': ['2026-09'],
    })

    const store = useBookingsStore()

    await store.load()

    const [first, second] = store.events

    // The window overlaps what is held: the same two rows come back, plus one
    // that was outside the original range.
    serve({
      '/api/events?*': [{ ...first }, { ...second }, event('2026-08-15')],
      '/api/events/months': ['2026-09'],
    })

    await store.ensureMonthLoaded(new Date(2026, 7, 1))

    expect(store.events).toHaveLength(3)
    expect(store.events.map((one) => one.id)).toEqual([...new Set(store.events.map((one) => one.id))])
    // Still in date order, so the list never has to sort again.
    expect(store.events.map((one) => one.date)).toEqual(['2026-08-15', '2026-09-26', '2026-10-17'])
  })
})

describe('failure', () => {
  it('leaves the loaded events alone when a window fails, and offers a retry', async () => {
    serve({
      '/api/events?*': [event('2026-09-26'), event('2026-10-17')],
      '/api/events/months': ['2026-09'],
    })

    const store = useBookingsStore()

    await store.load()

    serve({ '/api/events/months': [] }, ['/api/events?'])

    await store.ensureMonthLoaded(new Date(2026, 5, 1))

    // The month has no marks, which is recoverable. An empty list is not.
    expect(store.events).toHaveLength(2)
    expect(store.status).toBe('ready')
    expect(store.windowStatus).toBe('failed')
    expect(store.hasFailed).toBe(true)

    serve({ '/api/events?*': [event('2026-06-12')], '/api/events/months': [] })

    await store.retry(new Date(2026, 5, 1))

    expect(store.events).toHaveLength(3)
    expect(store.hasFailed).toBe(false)
  })

  it('retries the first load when that is what failed', async () => {
    serve({}, ['/api/events'])

    const store = useBookingsStore()

    await store.load()

    expect(store.status).toBe('failed')
    expect(store.hasFailed).toBe(true)

    serve({ '/api/events?*': [event('2026-09-26')], '/api/events/months': ['2026-09'] })

    await store.retry()

    expect(store.status).toBe('ready')
    expect(store.events).toHaveLength(1)
  })
})
