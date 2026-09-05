import { describe, expect, it } from 'vitest'
import { settle } from '@/lib/testHelpers'
import { mountWithCleanup } from '@/lib/testMount'
import MonthJumpSheet from '@/components/bookings/MonthJumpSheet.vue'

// The year strip and the dots come from one source, GET /api/events/months, so
// they cannot disagree. Before this they could: the strip was the current year
// minus one to plus four, hardcoded, and an artist with a 2031 wedding had a
// dot she could not navigate to.

const mount = mountWithCleanup()

async function years(months: string[]): Promise<number[]> {
  // The panel is teleported to the body, so it is read from the document
  // rather than from the mount host.
  await mount(MonthJumpSheet, '/bookings', {
    open: true,
    month: new Date(2026, 8, 1),
    monthsWithWork: new Set(months),
  })

  await settle()

  return [...document.querySelectorAll('[role="dialog"] [aria-pressed]')]
    .map((button) => Number(button.textContent?.trim()))
}

describe('the year strip', () => {
  it('reaches a year further out than the old hardcoded range did', async () => {
    const strip = await years(['2026-09', '2031-06'])

    expect(strip).toContain(2031)
    expect(strip).toContain(2032)
  })

  it('reaches back to the earliest month the account holds', async () => {
    const strip = await years(['2020-01', '2026-09'])

    expect(strip[0]).toBe(2019)
    expect(strip).toContain(2020)
  })

  it('always includes this year, even when every event is elsewhere', async () => {
    expect(await years(['2031-06'])).toContain(new Date().getFullYear())
  })

  it('falls back to a year either side when the diary is empty', async () => {
    const current = new Date().getFullYear()

    expect(await years([])).toEqual([current - 1, current, current + 1])
  })

  it('is sorted and has no gaps', async () => {
    const strip = await years(['2024-03', '2027-11'])

    expect(strip).toEqual([2023, 2024, 2025, 2026, 2027, 2028])
  })
})

// The dots come from the same set, which is the point of deriving the strip
// from it: a month with work always has a year to reach it from.
it('dots a month that has work', async () => {
  await mount(MonthJumpSheet, '/bookings', {
    open: true,
    month: new Date(2026, 8, 1),
    monthsWithWork: new Set(['2026-09']),
  })

  await settle()

  const september = [...document.querySelectorAll('[role="dialog"] button')]
    .find((button) => button.textContent?.trim().startsWith('Sep'))

  expect(september?.getAttribute('aria-label')).toContain('has bookings')
})
