import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { element, settle } from '@/lib/testHelpers'
import { mountWithCleanup } from '@/lib/testMount'
import { dayKey } from '@/lib/monthGrid'
import { useBookingsStore } from '@/stores/bookings'
import BookingsView from '@/views/bookings/BookingsView.vue'
import type { BookingEvent } from '@/types/bookings'

// The screen's own behaviour: what a tap on a day does, what a second tap
// does, and that the two halves stay two views of one set of events.
//
// The fixtures are not used here. The store is filled directly, so the dates
// are fixed and the assertions do not move with the calendar.

const mount = mountWithCleanup()

const today = new Date(2026, 8, 5)
const busy = new Date(2026, 8, 26)
const other = new Date(2026, 8, 12)

let nextId = 0

function event(date: Date, over: Partial<BookingEvent> = {}): BookingEvent {
  nextId += 1

  return {
    id: nextId,
    bookingId: nextId,
    type: 'main',
    label: null,
    date: dayKey(date),
    startTime: '06:30',
    venueName: 'Thornleigh Hall',
    city: 'Marlow',
    clientName: `Client ${nextId}`,
    stage: 'confirmed',
    totalMinor: 78000,
    currency: 'GBP',
    waitingOn: null,
    lastTouchedAt: new Date(2026, 8, 1).toISOString(),
    ...over,
  }
}

// The case business logic 19.1 calls out: a Saturday carrying one confirmed
// booking and three live enquiries, plus something on another day so the
// unfiltered list has more than one thing in it.
function seed(): BookingEvent[] {
  return [
    event(busy, { clientName: 'Amelia Trent', stage: 'confirmed' }),
    event(busy, { clientName: 'Rosie Kerr', stage: 'possible', totalMinor: 0 }),
    event(busy, { clientName: 'Nadia Iqbal', stage: 'possible', totalMinor: 0 }),
    event(busy, { clientName: 'Charlotte Dean', stage: 'quoted', totalMinor: 0 }),
    event(other, { clientName: 'Priya Raman', stage: 'confirmed' }),
  ]
}

beforeEach(() => {
  setActivePinia(createPinia())
  // Date only. The component measures heights inside requestAnimationFrame and
  // recentres the swipe track on a timer, so faking those as well would stop
  // the screen settling and the assertions below would be about a half-built
  // page. Without this the suite passes only on days that happen to be 5
  // September 2026.
  vi.useFakeTimers({ toFake: ['Date'] })
  vi.setSystemTime(today)
})

afterEach(() => {
  vi.useRealTimers()
})

async function open() {
  const mounted = await mount(BookingsView, '/bookings')

  useBookingsStore().events = seed()
  useBookingsStore().status = 'ready'
  await settle()

  return mounted
}

function cell(host: HTMLElement, date: Date): HTMLElement {
  // The middle panel of the three-month swipe track is the one on screen.
  const panels = host.querySelectorAll('[role="grid"]')

  return element(panels[1] as HTMLElement, `[data-key="${dayKey(date)}"]`)
}

function names(host: HTMLElement): string[] {
  return Array.from(host.querySelectorAll('[data-date] .booking-row__name'))
    .map((node) => node.textContent?.trim() ?? '')
}

describe('the bookings screen', () => {
  it('lists what is coming up, and marks the day that carries both a booking and enquiries', async () => {
    const { host } = await open()

    expect(names(host)).toContain('Priya Raman')
    expect(names(host)).toContain('Amelia Trent')

    const busyCell = cell(host, busy)

    // A filled circle and a small orange number say nothing on their own, so
    // the whole day is in the label.
    expect(busyCell.getAttribute('aria-label')).toContain('1 confirmed booking')
    expect(busyCell.getAttribute('aria-label')).toContain('3 enquiries')
    expect(busyCell.className).toContain('is-confirmed')
  })

  it('says so in words when a day has nothing on it', async () => {
    const { host } = await open()

    // Paired with the assertion above, so that neither can quietly stop being
    // about anything if the wording moves.
    expect(cell(host, new Date(2026, 8, 15)).getAttribute('aria-label')).toContain('nothing on')
    expect(cell(host, busy).getAttribute('aria-label')).not.toContain('nothing on')
  })

  it('filters the list to a day when that day is tapped', async () => {
    const { host } = await open()

    cell(host, busy).click()
    await settle()

    expect(names(host)).toEqual([
      'Amelia Trent',
      'Rosie Kerr',
      'Nadia Iqbal',
      'Charlotte Dean',
    ])
    expect(names(host)).not.toContain('Priya Raman')
    expect(host.textContent).toContain('Show all bookings')
  })

  it('clears the filter when the same day is tapped again', async () => {
    const { host } = await open()

    cell(host, busy).click()
    await settle()
    cell(host, busy).click()
    await settle()

    expect(names(host)).toContain('Priya Raman')
    expect(host.textContent).not.toContain('Show all bookings')
  })

  it('clears the filter from the show all button', async () => {
    const { host } = await open()

    cell(host, busy).click()
    await settle()

    const showAll = Array.from(host.querySelectorAll('button'))
      .find((button) => button.textContent?.includes('Show all bookings'))

    showAll?.click()
    await settle()

    expect(names(host)).toContain('Priya Raman')
    expect(host.textContent).not.toContain('Show all bookings')
  })

  it('offers an empty state for a day with nothing on it, rather than a blank panel', async () => {
    const { host } = await open()

    cell(host, new Date(2026, 8, 15)).click()
    await settle()

    expect(host.textContent).toContain('Nothing on this day')
  })

  it('changes month with the arrows', async () => {
    const { host } = await open()

    const next = Array.from(host.querySelectorAll('button'))
      .find((button) => button.getAttribute('aria-label') === 'Next month')

    expect(host.textContent).toContain('September 2026')

    next?.click()
    await settle()

    expect(host.textContent).toContain('October 2026')
  })

  it('hides and shows the calendar as a disclosure, not a pressed toggle', async () => {
    const { host } = await open()

    const toggle = Array.from(host.querySelectorAll('button'))
      .find((button) => button.getAttribute('aria-expanded') === 'true'
        && button.hasAttribute('aria-controls')
        && button.textContent?.includes('Hide'))

    expect(toggle).toBeTruthy()
    // A label that names the action and a pressed state contradict each other.
    expect(toggle?.hasAttribute('aria-pressed')).toBe(false)

    toggle?.click()
    await settle()

    expect(toggle?.getAttribute('aria-expanded')).toBe('false')
    expect(toggle?.textContent).toContain('Show')
  })

  it('names the view the mode button would give, not the one on screen', async () => {
    const { host } = await open()

    const mode = Array.from(host.querySelectorAll('button'))
      .find((button) => button.getAttribute('aria-label') === 'Show this week only')

    expect(mode?.textContent?.trim()).toBe('Week')

    mode?.click()
    await settle()

    // One row now, and the button offers the way back.
    const panels = host.querySelectorAll('[role="grid"]')

    expect((panels[1] as HTMLElement).querySelectorAll('[role="row"]')).toHaveLength(1)
    expect(mode?.textContent?.trim()).toBe('Month')
  })

  it('keeps exactly one day in the tab order', async () => {
    const { host } = await open()

    const panel = host.querySelectorAll('[role="grid"]')[1] as HTMLElement
    const tabbable = panel.querySelectorAll('[role="gridcell"][tabindex="0"]')

    expect(tabbable).toHaveLength(1)
    // Today, because nothing is selected and this month contains it.
    expect(tabbable[0].getAttribute('data-key')).toBe(dayKey(today))
  })
})
