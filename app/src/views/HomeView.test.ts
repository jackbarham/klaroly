import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import AttentionView from '@/views/AttentionView.vue'
import HomeView from '@/views/HomeView.vue'
import { jsonResponse, settle } from '@/lib/testHelpers'
import { mountWithCleanup } from '@/lib/testMount'
import { activeTabKey, sectionKey } from '@/lib/navigation'
import type { AttentionRow, HomeMeta, HomeSummary, UpcomingEvent } from '@/types/home'

// The screen as a whole.
//
// **What this file may and may not assert.** /home renders the attention list
// twice, once capped and once not, and lets a container query pick. jsdom does
// not evaluate container queries, so both copies are in the DOM here and
// @split:hidden hides neither: any assertion that counted rows or read a band
// heading would see double and could pass while the screen is wrong. So row
// counts and headings live in AttentionList.test.ts with an explicit limit, and
// this file asserts only which BLOCKS and which wrappers exist.

const mount = mountWithCleanup()

function row(id: number, overrides: Partial<AttentionRow> = {}): AttentionRow {
  return {
    booking_id: id,
    waiting_on: 'artist_price',
    party: 'artist',
    client_name: `Client ${id}`,
    contact_id: id,
    stage: 'possible',
    currency: 'GBP',
    event_date: '2027-07-04',
    trial_date: null,
    last_touched_at: '2026-09-01T09:00:00.000000Z',
    created_at: '2026-09-01T09:00:00.000000Z',
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
    venue_name: 'Penbury Manor',
    city: 'Hitchin',
    client_name: 'Nadia Kerrigan',
    stage: 'confirmed',
    party_size: 5,
    travel_duration_s: null,
    travel_distance_m: null,
    ...overrides,
  }
}

const emptyMoney: HomeSummary['money'] = {
  currency: 'GBP',
  basis: 'payments',
  excludes_other_currencies: false,
  owed_minor: 0,
  owed_count: 0,
  snoozed_minor: 0,
  outstanding: { due_minor: 0, overdue_minor: 0, snoozed_minor: 0 },
  booked_ahead_minor: 0,
  booked_ahead_count: 0,
  provisional_minor: 0,
  provisional_count: 0,
  periods: {
    this_month: { from: '2026-09-01', to: '2026-09-06', value_minor: 0, booking_count: 0, average_value_minor: 0 },
    three_months: { from: '2026-06-07', to: '2026-09-06', value_minor: 0, booking_count: 0, average_value_minor: 0 },
    twelve_months: { from: '2025-09-07', to: '2026-09-06', value_minor: 0, booking_count: 0, average_value_minor: 0 },
    business_year: { from: '2026-04-06', to: '2026-09-06', value_minor: 0, booking_count: 0, average_value_minor: 0 },
  },
}

const meta: HomeMeta = {
  features: { invoicing: true, payment_tracking: true },
  attention: { total: 0, returned: 0, truncated: false },
  today: '2026-09-06',
  timezone: 'Europe/London',
}

function answer(summary: Partial<HomeSummary>, over: Partial<HomeMeta> = {}): void {
  const attention = summary.attention ?? []

  vi.mocked(globalThis.fetch).mockResolvedValue(jsonResponse(200, {
    data: { attention, upcoming: summary.upcoming ?? [], money: summary.money ?? emptyMoney },
    meta: {
      ...meta,
      ...over,
      attention: { total: attention.length, returned: attention.length, truncated: false, ...over.attention },
    },
  }))
}

beforeEach(() => {
  globalThis.fetch = vi.fn()
  window.localStorage.clear()
})

afterEach(() => {
  vi.restoreAllMocks()
  window.localStorage.clear()
})

describe('the blocks', () => {
  it('draws all three in the default order', async () => {
    answer({ attention: [row(1)], upcoming: [event()] })

    const { host } = await mount(HomeView, '/')

    await settle()

    // Which blocks, by their headings, and in which order. Not which rows: see
    // the note at the top of this file.
    expect([...host.querySelectorAll('h2')].map((found) => found.textContent?.trim()))
      .toEqual(['Next up', 'Attention', 'Money'])
  })

  /**
   * **The empty-block rule**, business logic 18.1: Attention and Next up
   * disappear when they are empty. Money never does, because a booking carries
   * a price whether or not anybody raised an invoice for it.
   */
  it('drops Next up when there is nothing coming, and never drops Money', async () => {
    answer({ attention: [row(1)], upcoming: [] })

    const { host } = await mount(HomeView, '/')

    await settle()

    expect(host.textContent).not.toContain('Next up')
    expect(host.textContent).toContain('Attention')
    expect(host.textContent).toContain('Money')
  })

  /**
   * The quiet week: nothing waiting, so the block is replaced by one line
   * rather than by nothing. An artist used to seeing four things who suddenly
   * sees none cannot tell a clear week from a bug.
   */
  it('draws the all-clear line on a quiet week and still draws the other blocks', async () => {
    answer({ attention: [], upcoming: [event()] })

    const { host } = await mount(HomeView, '/')

    await settle()

    expect(host.textContent).toContain('Nothing needs you today')
    expect(host.textContent).toContain('Next up')
    expect(host.textContent).toContain('Money')
  })

  /**
   * The empty account is not a quiet week: every block is empty and the money
   * figures are all nought, so the screen is a first-run state instead of three
   * empty blocks.
   */
  it('draws the first-run state on an account with nothing at all', async () => {
    answer({ attention: [], upcoming: [], money: emptyMoney })

    const { host } = await mount(HomeView, '/')

    await settle()

    expect(host.textContent).toContain('Nothing here yet')
    expect(host.textContent).toContain('Add a booking')
    expect(host.textContent).toContain('Add an enquiry')
    // The rate card, named as the sit-down job that makes every later quote a
    // few taps.
    expect(host.textContent).toContain('Rate card')

    // And none of the three blocks, because it replaces all of them.
    expect(host.textContent).not.toContain('Nothing needs you today')
    expect(host.textContent).not.toContain('Booked ahead')

    // What it deliberately does not do: no tour, no checklist, no profile.
    expect(host.querySelectorAll('input')).toHaveLength(0)
  })
})

describe('the two arrangements', () => {
  /**
   * Both copies of the list are in the DOM and the container query picks one.
   * jsdom evaluates no container queries, so this asserts the WRAPPERS exist
   * rather than what is inside them.
   */
  it('renders a capped preview and an uncapped list, one per width', async () => {
    answer({ attention: [row(1), row(2), row(3), row(4), row(5)], upcoming: [] })

    const { host } = await mount(HomeView, '/')

    await settle()

    expect(host.querySelector('[data-preview]')).not.toBeNull()
    expect(host.querySelector('[data-full]')).not.toBeNull()
  })
})

describe('the order', () => {
  it('follows what the artist left in Adjust', async () => {
    window.localStorage.setItem('klaroly.home.view', JSON.stringify({
      order: ['money', 'attention', 'next'],
      previewCount: 4,
      period: 'this_month',
    }))

    answer({ attention: [row(1)], upcoming: [event()] })

    const { host } = await mount(HomeView, '/')

    await settle()

    // **The DOM order is the artist's order**, which is what makes focus order
    // and screen-reader order match what is on screen.
    expect([...host.querySelectorAll('h2')].map((found) => found.textContent?.trim()))
      .toEqual(['Money', 'Attention', 'Next up'])
  })
})

describe('the day everything is counted against', () => {
  /**
   * **meta.today, not the device's clock.** The server already decided what is
   * overdue using the account's day, so a phone in another timezone doing its
   * own arithmetic would put a number on a row that the money block disagrees
   * with.
   */
  it('uses the account day the response was computed against', async () => {
    answer({
      attention: [row(1, {
        waiting_on: 'client_balance',
        party: 'client',
        outstanding_minor: 34000,
        invoice_total_minor: 68000,
        due_on: '2026-08-28',
      })],
      upcoming: [],
    }, { today: '2026-09-06' })

    const { host } = await mount(HomeView, '/')

    await settle()

    // Nine days from 28 August to 6 September, which is the server's day and
    // not the machine running this test.
    expect(host.textContent).toContain('9 days late')
  })
})

describe('the attention route', () => {
  it('renders the full list and is reachable by URL', async () => {
    answer({ attention: [row(1), row(2)], upcoming: [] })

    const { host } = await mount(AttentionView, '/attention')

    await settle()

    expect(host.querySelectorAll('li')).toHaveLength(2)
    // One list, not two: there is no cap here, so there is nothing to preview.
    expect(host.querySelector('[data-preview]')).toBeNull()
    // And a way back, which is what a route buys over a view flag.
    expect(host.querySelector('a[href="/"]')).not.toBeNull()
  })

  /**
   * **The one line in navigation.ts, and the only thing between correct and
   * silently broken.** Without it sectionKey returns null, activeTabKey returns
   * null, activeTabIndex is minus one, and the tab bar's pill hides itself with
   * no error anywhere.
   */
  it('marks Home in the navigation, the way a booking marks Bookings', () => {
    expect(sectionKey('attention')).toBe('home')
    expect(activeTabKey('attention')).toBe('home')
  })
})
