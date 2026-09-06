import { describe, expect, it } from 'vitest'
import MoneyBlock from '@/components/home/MoneyBlock.vue'
import { element } from '@/lib/testHelpers'
import { mountWithCleanup } from '@/lib/testMount'
import type { HomeMoney, PeriodTotals } from '@/types/home'

// Business logic 18.3, and the two figures that must never be added together.

const mount = mountWithCleanup()

function period(value: number, count: number, average: number): PeriodTotals {
  return { from: '2026-09-01', to: '2026-09-06', value_minor: value, booking_count: count, average_value_minor: average }
}

function money(overrides: Partial<HomeMoney> = {}): HomeMoney {
  return {
    currency: 'GBP',
    basis: 'payments',
    excludes_other_currencies: false,
    owed_minor: 34000,
    owed_count: 1,
    snoozed_minor: 0,
    outstanding: { due_minor: 51000, overdue_minor: 138000, snoozed_minor: 20000 },
    booked_ahead_minor: 219000,
    booked_ahead_count: 3,
    provisional_minor: 62000,
    provisional_count: 1,
    periods: {
      this_month: period(45000, 1, 45000),
      three_months: period(109000, 3, 36333),
      twelve_months: period(940000, 22, 42727),
      business_year: period(520000, 12, 43333),
    },
    ...overrides,
  }
}

function labels(host: HTMLElement): string[] {
  return [...host.querySelectorAll('dt')].map((found) => found.textContent?.trim() ?? '')
}

function figures(host: HTMLElement): string[] {
  return [...host.querySelectorAll('dd')].map((found) => found.firstChild?.textContent?.trim() ?? '')
}

describe('the three toggle states', () => {
  it('draws everything with invoicing on and payments tracked', async () => {
    const { host } = await mount(MoneyBlock, '/', { money: money(), period: 'this_month' })

    expect(labels(host)).toEqual(['Received', 'Outstanding', 'Booked ahead', 'Provisional'])
    expect(host.textContent).toContain('£340 owed to you')
  })

  /**
   * With invoicing off, the API sends null for both, because nothing was ever
   * given a due date. The block draws no headline and no Outstanding, and
   * everything else stays: **the money block is never removed by a feature
   * toggle**, because a booking carries a price whether or not anybody raised
   * an invoice for it.
   */
  it('drops the headline and Outstanding with invoicing off, and keeps the block', async () => {
    const { host } = await mount(MoneyBlock, '/', {
      money: money({ owed_minor: null, owed_count: null, snoozed_minor: null, outstanding: null }),
      period: 'this_month',
    })

    expect(labels(host)).toEqual(['Received', 'Booked ahead', 'Provisional'])
    expect(host.textContent).not.toContain('owed to you')
    // The block is still here, which is the half that matters.
    expect(host.textContent).toContain('Money')
    expect(host.textContent).toContain('Booked ahead')
  })

  /**
   * With payment tracking off as well, the period figure stops being cash and
   * becomes value. Those are different numbers, so the block says which it is
   * showing rather than drawing "Received" over a figure that is nothing of the
   * sort.
   */
  it('says Booked in this period rather than Received when nothing is tracked', async () => {
    const { host } = await mount(MoneyBlock, '/', {
      money: money({
        basis: 'booking_value',
        owed_minor: null,
        owed_count: null,
        snoozed_minor: null,
        outstanding: null,
      }),
      period: 'this_month',
    })

    expect(labels(host)).toEqual(['Booked in this period', 'Booked ahead', 'Provisional'])
    // **Never "Received: £0" on an account that records no payments.**
    expect(host.textContent).not.toContain('Received')
    // And the footnote says what turning payment tracking on would add, which
    // is discovery arriving where it is useful.
    expect(host.textContent).toContain('Turn on payment tracking')
  })

  /**
   * The block reads the payload and never the auth store: with an auth store
   * that has never been populated, what is drawn still follows the response's
   * own nulls, which the server set from meta.features.
   */
  it('follows the payload rather than any store opinion about the features', async () => {
    const { host } = await mount(MoneyBlock, '/', {
      money: money({ owed_minor: null, owed_count: null, snoozed_minor: null, outstanding: null }),
      period: 'this_month',
    })

    expect(labels(host)).not.toContain('Outstanding')
  })
})

describe('the two figures that are not comparable', () => {
  /**
   * **outstanding.snoozed_minor is a subset of overdue_minor, not a third
   * bucket.** due plus overdue is the whole of outstanding, and the snoozed
   * figure names the part of overdue nobody is being chased for.
   */
  it('adds due and overdue only, and never the snoozed part', async () => {
    const { host } = await mount(MoneyBlock, '/', { money: money(), period: 'this_month' })

    // £510 due plus £1,380 overdue is £1,890. Adding the £200 snoozed as well
    // would read £2,090, which is the mistake this asserts against.
    expect(figures(host)[1]).toBe('£1,890')
    expect(host.textContent).not.toContain('£2,090')
  })

  /**
   * owed_minor and outstanding.overdue_minor answer different questions: owed
   * is balances past their date on weddings that have happened, and overdue
   * also counts unpaid deposits, which are late money that is not a balance.
   * £340 and £1,380 here, and the difference is a deposit.
   */
  it('draws the headline and the overdue figure as separate things', async () => {
    const { host } = await mount(MoneyBlock, '/', { money: money(), period: 'this_month' })

    expect(host.textContent).toContain('£340 owed to you')
    expect(host.textContent).toContain('£1,380 overdue')
    // The caption under the headline counts weddings, not the overdue total, so
    // nothing implies one figure is part of the other.
    expect(host.textContent).toContain('From 1 wedding that has already happened')
  })
})

describe('the snoozed figure', () => {
  it('is named under the headline when there is one', async () => {
    const { host } = await mount(MoneyBlock, '/', {
      money: money({ snoozed_minor: 20000 }),
      period: 'this_month',
    })

    expect(host.textContent).toContain('plus £200 snoozed')
  })

  it('is not drawn at nought', async () => {
    const { host } = await mount(MoneyBlock, '/', { money: money({ snoozed_minor: 0 }), period: 'this_month' })

    expect(host.textContent).not.toContain('snoozed')
  })
})

describe('the period selector', () => {
  /**
   * **It governs three figures and not five**, which is the likeliest
   * misreading of the most scrutinised numbers in the app, and the line under
   * the grid is what stops it.
   */
  it('changes the period figure and leaves the other three alone', async () => {
    const first = await mount(MoneyBlock, '/', { money: money(), period: 'this_month' })
    const firstFigures = figures(first.host)

    const second = await mount(MoneyBlock, '/', { money: money(), period: 'twelve_months' })
    const secondFigures = figures(second.host)

    // The period figure moves.
    expect(firstFigures[0]).toBe('£450')
    expect(secondFigures[0]).toBe('£9,400')

    // Outstanding, booked ahead and provisional do not.
    expect(secondFigures.slice(1)).toEqual(firstFigures.slice(1))
  })

  it('emits the period it was asked for', async () => {
    const { host } = await mount(MoneyBlock, '/', { money: money(), period: 'this_month' })

    const pressed = [...host.querySelectorAll('button')].filter((found) => found.getAttribute('aria-pressed') === 'true')

    expect(pressed).toHaveLength(1)
    expect(pressed[0].textContent?.trim()).toBe('This month')
  })

  it('says which figures it governs', async () => {
    const { host } = await mount(MoneyBlock, '/', { money: money(), period: 'this_month' })

    expect(element(host, 'section').textContent)
      .toContain('Outstanding, booked ahead and provisional are as of today')
  })
})
