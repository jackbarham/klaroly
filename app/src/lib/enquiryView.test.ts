import { afterEach, expect, it, vi } from 'vitest'
import { defaultSettings, readSettings, writeSettings } from '@/lib/enquiryView'

// The five view settings, and the two ways storage lets you down.
//
// The mechanism is src/lib/viewSettings.ts, shared with the contacts menu, and
// these run against this screen's own values because that is what the screen
// reads. The contacts file has the same pair of tests for the same reason: if
// the shared reader ever stopped wrapping the accessor, both would say so.

const key = 'klaroly.enquiries.view'

// Unstubbed before clearing, or the clear runs against the refusing stub the
// last test left behind and throws in the teardown instead of the test.
afterEach(() => {
  vi.unstubAllGlobals()
  window.localStorage.clear()
})

// A storage that fails the way a blocked one does: every accessor throws, not
// just the parse.
function refusingStorage(): Storage {
  const refuse = () => {
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

it('starts at the defaults with nothing stored', () => {
  expect(readSettings()).toEqual(defaultSettings)
})

// Neglect is the default order because it is the one that answers the screen's
// own question, and the clash line is on because a control that hides a
// double-booked Saturday is a control that gets left off.
it('defaults to neglect, with the clash line and the totals on and the archive off', () => {
  expect(defaultSettings.sort).toBe('staleness')
  expect(defaultSettings.showClashes).toBe(true)
  expect(defaultSettings.showTotals).toBe(true)
  expect(defaultSettings.showLost).toBe(false)
  expect(defaultSettings.showSource).toBe(false)
})

it('survives a reload', () => {
  writeSettings({ ...defaultSettings, sort: 'date', showLost: true })

  expect(readSettings()).toEqual({ ...defaultSettings, sort: 'date', showLost: true })
})

/**
 * A value written by an older build, or by something else on this origin,
 * cannot reach the sort function. "recent" is the contacts key's value, which
 * is exactly what somebody copying that file would leave behind.
 */
it('replaces a sort it does not recognise without losing the ones it does', () => {
  window.localStorage.setItem(key, JSON.stringify({ sort: 'recent', showLost: true }))

  const settings = readSettings()

  expect(settings.sort).toBe(defaultSettings.sort)
  expect(settings.showLost).toBe(true)
})

it('falls back to the defaults when the stored value is not an object', () => {
  window.localStorage.setItem(key, '"staleness"')

  expect(readSettings()).toEqual(defaultSettings)
})

/**
 * The reason this pair exists.
 *
 * localStorage is not a plain object that is either empty or full: in a
 * private window, in a browser set to block site data and inside a thumbnail
 * renderer, touching the accessor itself throws before there is any JSON to
 * fail on. A try around JSON.parse alone would still take the screen down.
 */
it('reads the defaults when the storage accessor itself throws', () => {
  vi.stubGlobal('localStorage', refusingStorage())

  expect(readSettings()).toEqual(defaultSettings)
})

it('writes without throwing when the storage accessor itself throws', () => {
  vi.stubGlobal('localStorage', refusingStorage())

  expect(() => writeSettings(defaultSettings)).not.toThrow()
})
