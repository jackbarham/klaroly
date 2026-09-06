import { afterEach, describe, expect, it, vi } from 'vitest'
import { defaultSettings, readSettings, writeSettings } from '@/lib/contactView'

// The four view settings, and the two ways storage lets you down.
//
// The second of those is the reason this file exists. localStorage is not a
// plain object that is either empty or full: in a private window, in a browser
// set to block site data, and inside a thumbnail renderer, touching the
// accessor at all throws, before there is any JSON to fail parsing. A screen
// that assumed otherwise would go blank in exactly the situations nobody tests
// by hand, so it is asserted here by making the accessor throw rather than by
// trusting that it cannot.

afterEach(() => {
  vi.unstubAllGlobals()
  window.localStorage.clear()
})

// A storage that fails the way a blocked one does.
function refusingStorage(): Storage {
  return {
    get length(): number {
      throw new Error('The operation is insecure.')
    },
    clear: () => {
      throw new Error('The operation is insecure.')
    },
    getItem: () => {
      throw new Error('The operation is insecure.')
    },
    key: () => {
      throw new Error('The operation is insecure.')
    },
    removeItem: () => {
      throw new Error('The operation is insecure.')
    },
    setItem: () => {
      throw new Error('The operation is insecure.')
    },
  }
}

describe('the view settings', () => {
  it('start at the defaults with nothing stored', () => {
    expect(readSettings()).toEqual(defaultSettings)
  })

  // The reload. Written by one call and read back by another, with nothing
  // held in memory between them, which is what a fresh page load does.
  it('survive a reload', () => {
    writeSettings({ sort: 'alpha', leadWith: 'booking', showInitials: false, showAmounts: false })

    expect(readSettings()).toEqual({
      sort: 'alpha',
      leadWith: 'booking',
      showInitials: false,
      showAmounts: false,
    })
  })

  // Paired with the assertion above, so that "they survive" cannot be passing
  // because the reader is returning what it was given: a different set has to
  // come back differently.
  it('come back as what was last written, not as the first thing written', () => {
    writeSettings({ sort: 'alpha', leadWith: 'booking', showInitials: false, showAmounts: false })
    writeSettings({ ...defaultSettings, sort: 'alpha' })

    expect(readSettings()).toEqual({ ...defaultSettings, sort: 'alpha' })
  })

  it('fall back to the defaults when the stored value is not an object', () => {
    window.localStorage.setItem('klaroly.contacts.view', '"alpha"')

    expect(readSettings()).toEqual(defaultSettings)
  })

  // A value written by an older version of this app, or by something else on
  // the same origin. Each field is checked rather than cast, so a sort mode
  // that no longer exists cannot reach the sort function.
  it('replaces a field it does not recognise without losing the ones it does', () => {
    window.localStorage.setItem(
      'klaroly.contacts.view',
      JSON.stringify({ sort: 'surname', leadWith: 'booking', showInitials: 'yes', showAmounts: false }),
    )

    expect(readSettings()).toEqual({
      sort: defaultSettings.sort,
      leadWith: 'booking',
      showInitials: defaultSettings.showInitials,
      showAmounts: false,
    })
  })

  // Something non-default is stored first, on purpose. Without it this would
  // pass whether the stub took effect or not, because an empty storage returns
  // the defaults too: the assertion would be true for the wrong reason and
  // would go on being true if the try/catch were deleted.
  it('reads the defaults when the storage accessor itself throws', () => {
    writeSettings({ ...defaultSettings, sort: 'alpha' })
    expect(readSettings().sort).toBe('alpha')

    vi.stubGlobal('localStorage', refusingStorage())

    expect(readSettings()).toEqual(defaultSettings)
  })

  // Failing to remember a preference is not worth taking the screen down for.
  // The setting still took effect; it simply will not survive the reload.
  it('writes without throwing when the storage accessor itself throws', () => {
    vi.stubGlobal('localStorage', refusingStorage())

    expect(() => writeSettings(defaultSettings)).not.toThrow()
  })
})
