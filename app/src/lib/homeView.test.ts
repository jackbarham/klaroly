import { afterEach, describe, expect, it, vi } from 'vitest'
import { defaultSettings, previewLimit, readSettings, writeSettings } from '@/lib/homeView'

// The third caller of src/lib/viewSettings.ts, and the first to store an array.

afterEach(() => {
  vi.unstubAllGlobals()
  window.localStorage.clear()
})

// A storage that fails the way a blocked one does, which is the shape
// src/lib/contactView.test.ts already uses: touching the accessor throws,
// before there is any JSON to fail parsing.
function refusingStorage(): Storage {
  const refuse = (): never => {
    throw new Error('The operation is insecure.')
  }

  return {
    get length(): number {
      return refuse()
    },
    clear: refuse,
    getItem: refuse,
    key: refuse,
    removeItem: refuse,
    setItem: refuse,
  }
}

describe('reading what was left', () => {
  it('survives a reload', () => {
    writeSettings({ order: ['money', 'attention', 'next'], previewCount: 6, period: 'twelve_months' })

    expect(readSettings()).toEqual({
      order: ['money', 'attention', 'next'],
      previewCount: 6,
      period: 'twelve_months',
    })
  })

  it('falls back to the defaults when nothing has been stored', () => {
    expect(readSettings()).toEqual(defaultSettings)
  })

  /**
   * **The whole accessor is wrapped, not only the parse.**
   *
   * A private window, a browser set to block site data and a thumbnail renderer
   * all make getItem itself throw on the way in, before there is any JSON to
   * fail on, so a try around JSON.parse alone would still take the screen down.
   */
  it('does not take the screen down when the storage accessor itself throws', () => {
    vi.stubGlobal('localStorage', refusingStorage())

    expect(readSettings()).toEqual(defaultSettings)
  })

  it('says nothing when a write fails', () => {
    vi.stubGlobal('localStorage', refusingStorage())

    expect(() => writeSettings(defaultSettings)).not.toThrow()
  })
})

describe('checking each field', () => {
  /**
   * **A permutation is not a list of allowed values.** Three known keys with a
   * duplicate would pass a per-item check and then render one block three times
   * and lose the other two, so the length, the membership and the absence of
   * duplicates are all checked and anything short of that falls back whole.
   */
  it('refuses an order that is not a permutation', () => {
    for (const order of [['money', 'money', 'money'], ['money', 'next'], ['money', 'next', 'nope'], 'money', null]) {
      window.localStorage.setItem('klaroly.home.view', JSON.stringify({ order }))

      expect(readSettings().order).toEqual(defaultSettings.order)
    }
  })

  it('accepts a real permutation', () => {
    window.localStorage.setItem('klaroly.home.view', JSON.stringify({ order: ['attention', 'money', 'next'] }))

    expect(readSettings().order).toEqual(['attention', 'money', 'next'])
  })

  it('refuses a preview count and a period an older build could have written', () => {
    window.localStorage.setItem('klaroly.home.view', JSON.stringify({ previewCount: 5, period: 'last_month' }))

    const settings = readSettings()

    expect(settings.previewCount).toBe(defaultSettings.previewCount)
    expect(settings.period).toBe(defaultSettings.period)
  })
})

describe('the preview limit', () => {
  it('is the count, and null when the artist has chosen All', () => {
    expect(previewLimit(4)).toBe(4)
    expect(previewLimit('all')).toBeNull()
  })
})
