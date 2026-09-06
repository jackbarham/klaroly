import { describe, expect, it } from 'vitest'
import { sectionsFor } from '@/lib/enquirySections'
import type { FeatureMap } from '@/types/auth'

// Which sections of the booking screen a record has earned, and the rule that
// beats the stage rule.

const allOn: FeatureMap = {
  enquiries: true,
  intake_forms: true,
  agreements: true,
  invoicing: true,
  payment_tracking: true,
  automation: true,
  travel_estimates: true,
  photos: true,
  feedback_requests: true,
}

function keys(...args: Parameters<typeof sectionsFor>): string[] {
  return sectionsFor(...args).map((section) => section.key)
}

describe('what a stage earns', () => {
  // An enquiry gets dates, party, notes and activity. The rest arrive when
  // they become the next useful thing.
  it('gives a new enquiry four sections', () => {
    expect(keys('new', allOn)).toEqual(['dates', 'party', 'notes', 'activity'])
  })

  it('gives it nothing more at in conversation', () => {
    expect(keys('in_conversation', allOn)).toEqual(['dates', 'party', 'notes', 'activity'])
  })

  // Price appears at Possible, which is the artist saying it is worth pricing.
  it('adds the price at Possible, and keeps it at Quoted', () => {
    expect(keys('possible', allOn)).toContain('price')
    expect(keys('quoted', allOn)).toContain('price')
  })

  /**
   * The rest arrive the moment it converts, saying "no agreement yet, send
   * one", which is discovery and a working control at once, arriving at the
   * exact moment it is the right thing to do.
   */
  it('adds the other five the moment it becomes a booking', () => {
    const before = keys('quoted', allOn)
    const after = keys('provisional', allOn)

    for (const key of ['payments', 'details', 'agreement', 'messages', 'photos']) {
      expect(before).not.toContain(key)
      expect(after).toContain(key)
    }
  })

  // A lost enquiry is ranked as though it were at Quoted: it has ended, and
  // what is useful about it is everything it had reached rather than nothing.
  it('shows a lost enquiry what it had reached', () => {
    expect(keys('lost', allOn)).toEqual(keys('quoted', allOn))
  })
})

/**
 * **The feature is checked before the stage, and that is not an optimisation.**
 *
 * Business logic 21 and 6: the app only ever asks about things the artist has
 * switched on, so with invoicing off nothing is ever waiting on a deposit. A
 * section for a switched-off feature is never drawn at any stage, and gating
 * the other way round would draw one the moment a record converted.
 */
describe('what a switched-off feature costs', () => {
  it('never draws the section, even at the stage that would earn it', () => {
    const off: FeatureMap = { ...allOn, invoicing: false, agreements: false }

    expect(keys('provisional', off)).not.toContain('payments')
    expect(keys('provisional', off)).not.toContain('agreement')
    // Paired, so "not drawn" cannot pass on a list that draws nothing.
    expect(keys('provisional', off)).toContain('messages')
  })

  // Nothing known about the features yet, which is what a screen sees before
  // /api/me has answered. It fails closed rather than drawing five sections
  // that may not exist.
  it('draws only the sections that are not a feature when the map is missing', () => {
    expect(keys('provisional', null)).toEqual(['dates', 'party', 'price', 'notes', 'activity'])
  })
})
