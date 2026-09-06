import { describe, expect, it } from 'vitest'
import AttentionBlock from '@/components/home/AttentionBlock.vue'
import AttentionList from '@/components/home/AttentionList.vue'
import { mountWithCleanup } from '@/lib/testMount'
import type { AttentionRow } from '@/types/home'

// **Row-level assertions live here and not in HomeView.test.ts**, and that is
// not a preference.
//
// /home renders this component twice, once capped and once not, and lets the
// container query pick. jsdom does not evaluate container queries, so under
// test both copies are in the DOM and @split:hidden hides neither. A test that
// counted rows or read a band heading on the mounted screen would see both and
// could pass while the screen is wrong. So this file mounts the component with
// an explicit limit, where there is exactly one of everything, and
// HomeView.test.ts asserts only which wrappers exist.

const mount = mountWithCleanup()

const today = new Date(2026, 8, 6)

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

const eight: AttentionRow[] = [
  row(1, { waiting_on: 'artist_not_held', party: 'artist', converted_at: '2026-08-21T09:00:00.000000Z' }),
  row(2, { waiting_on: 'client_balance', party: 'client', outstanding_minor: 34000, invoice_total_minor: 68000, due_on: '2026-08-28' }),
  row(3, { waiting_on: 'client_deposit', party: 'client', outstanding_minor: 17000, due_on: '2026-09-02' }),
  row(4, { waiting_on: 'artist_enquiry_cold', party: 'artist' }),
  row(5, { waiting_on: 'artist_price', party: 'artist' }),
  row(6, { waiting_on: 'client_signature', party: 'client', sent_at: '2026-08-26T09:00:00.000000Z' }),
  row(7, { waiting_on: 'artist_price', party: 'artist' }),
  row(8, { waiting_on: 'client_signature', party: 'client', sent_at: '2026-08-20T09:00:00.000000Z' }),
]

function headings(host: HTMLElement): string[] {
  return [...host.querySelectorAll('h3')].map((found) => found.textContent?.trim() ?? '')
}

describe('the list', () => {
  it('draws the capped rows, grouped, with a client row in the preview', async () => {
    const { host } = await mount(AttentionList, '/', { rows: eight, today, limit: 4 })

    expect(host.querySelectorAll('li')).toHaveLength(4)

    // The first four of decision 217's order are not held, balance, deposit and
    // cold: two of the artist's and two of the client's.
    expect(host.textContent).toContain('Client 1')
    expect(host.textContent).toContain('Client 2')
    expect(host.textContent).not.toContain('Client 5')
  })

  /**
   * **The band headings count what is DRAWN, not the account total.** A heading
   * reading "4 need you" above two rows reads as a broken list; the heading's
   * own "See all 8" is what carries the total.
   */
  it('counts what is drawn in the band headings', async () => {
    const { host } = await mount(AttentionList, '/', { rows: eight, today, limit: 4 })

    expect(headings(host)).toEqual(['2 need you', '2 waiting on clients'])
  })

  it('counts the whole list when there is no cap', async () => {
    const { host } = await mount(AttentionList, '/', { rows: eight, today, limit: null })

    expect(host.querySelectorAll('li')).toHaveLength(8)
    expect(headings(host)).toEqual(['4 need you', '4 waiting on clients'])
  })

  it('writes both lines from the payload, with no copy from the server', async () => {
    const { host } = await mount(AttentionList, '/', { rows: [eight[1]], today, limit: null })

    expect(host.textContent).toContain("Client 2's balance is overdue")
    expect(host.textContent).toContain('£340 of £680')
    // Derived here from the due date, never received as a number.
    expect(host.textContent).toContain('9 days late')
  })

  /**
   * The two inline writes do not exist, so neither button is drawn. Asserted
   * against the two values that will carry them, so the day they are added this
   * test says where.
   *
   * A dead Snooze would be worse than none: decision 27's whole argument is
   * that an artist who cannot stop the chasers marks the invoice paid, so one
   * that visibly does nothing causes the corruption it exists to prevent.
   */
  it('carries no inline action on any row, including the two that will get one', async () => {
    const { host } = await mount(AttentionList, '/', {
      rows: [eight[0], eight[1]],
      today,
      limit: null,
    })

    expect(host.textContent).toContain("Client 1's date is not held")
    expect(host.textContent).toContain("Client 2's balance is overdue")
    expect(host.querySelectorAll('button')).toHaveLength(0)
  })

  // The row is a link and not a button, so the two writes can be added beside
  // it without rebuilding the markup (decision 240).
  it('makes each row a link to its booking', async () => {
    const { host } = await mount(AttentionList, '/', { rows: [eight[0]], today, limit: null })

    expect(host.querySelector('a')?.getAttribute('href')).toBe('/bookings/1')
  })
})

describe('the See all link', () => {
  it('shows the real total whatever the cap', async () => {
    const { host } = await mount(AttentionBlock, '/', {
      rows: eight,
      today,
      limit: 4,
      total: 8,
    })

    // Eight, not four: a preview that quietly showed four of eight would be the
    // amounts-owed switch problem on the screen where it would hurt most.
    expect(host.textContent).toContain('See all 8')
    expect(host.querySelector('a[href="/attention"]')).not.toBeNull()
  })

  it('disappears when the total is at or under the cap', async () => {
    const { host } = await mount(AttentionBlock, '/', {
      rows: eight.slice(0, 4),
      today,
      limit: 4,
      total: 4,
    })

    expect(host.querySelector('a[href="/attention"]')).toBeNull()
    // The presence half: the block is drawn, so this is not passing on an
    // empty render.
    expect(host.textContent).toContain('Attention')
  })

  it('never draws one when the artist has chosen All', async () => {
    const { host } = await mount(AttentionBlock, '/', {
      rows: eight,
      today,
      limit: null,
      total: 8,
    })

    expect(host.querySelector('a[href="/attention"]')).toBeNull()
  })
})

describe('the all-clear', () => {
  /**
   * One line rather than nothing. An artist used to seeing four things who
   * suddenly sees none cannot tell a clear week from a bug.
   */
  it('replaces the block when nothing is waiting, and goes the moment something is', async () => {
    const quiet = await mount(AttentionBlock, '/', { rows: [], today, limit: 4, total: 0 })

    expect(quiet.host.textContent).toContain('Nothing needs you today')
    expect(quiet.host.querySelectorAll('li')).toHaveLength(0)
  })
})
