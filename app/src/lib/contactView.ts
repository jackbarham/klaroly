// The four things the view menu changes, and where they are kept.
//
// They are a property of this device rather than of the account: they say how
// one person likes to read their own list, not anything about the business, so
// there is no column, no request and nothing to synchronise. One key holding
// one object, so a fifth setting is a field rather than a fifth key.

// How the list is ordered. Recency is the working order, with the next job at
// the top; A to Z is for finding somebody whose name you know.
export type SortMode = 'recent' | 'alpha'

// Which of the row's two lines is the strong one. In this business the
// memorable handle is often the job rather than the person, so the wedding can
// take the top line and the name can drop to the muted one. Nothing else about
// the row moves.
export type LeadWith = 'name' | 'booking'

export interface ViewSettings {
  sort: SortMode
  leadWith: LeadWith
  showInitials: boolean
  showAmounts: boolean
}

const storageKey = 'klaroly.contacts.view'

export const defaultSettings: ViewSettings = {
  sort: 'recent',
  leadWith: 'name',
  showInitials: true,
  showAmounts: true,
}

function isSortMode(value: unknown): value is SortMode {
  return value === 'recent' || value === 'alpha'
}

function isLeadWith(value: unknown): value is LeadWith {
  return value === 'name' || value === 'booking'
}

function boolean(value: unknown, fallback: boolean): boolean {
  return typeof value === 'boolean' ? value : fallback
}

/**
 * The settings as they were left, or the defaults.
 *
 * Every read is wrapped, and not only the parse. A private window, a browser
 * set to block site data and a thumbnail renderer all make the accessor itself
 * throw on the way in, before there is any JSON to fail on, so a try around
 * JSON.parse alone would still take the screen down. The value is also checked
 * field by field rather than cast, because what comes back is a string written
 * by an older version of this app or by something else on the same origin, and
 * a `sort` of "surname" would otherwise reach the sort function.
 */
export function readSettings(): ViewSettings {
  try {
    const stored = window.localStorage.getItem(storageKey)

    if (stored === null) {
      return { ...defaultSettings }
    }

    const parsed: unknown = JSON.parse(stored)

    if (typeof parsed !== 'object' || parsed === null) {
      return { ...defaultSettings }
    }

    const values = parsed as Partial<Record<keyof ViewSettings, unknown>>

    return {
      sort: isSortMode(values.sort) ? values.sort : defaultSettings.sort,
      leadWith: isLeadWith(values.leadWith) ? values.leadWith : defaultSettings.leadWith,
      showInitials: boolean(values.showInitials, defaultSettings.showInitials),
      showAmounts: boolean(values.showAmounts, defaultSettings.showAmounts),
    }
  } catch {
    return { ...defaultSettings }
  }
}

// Failing to remember a preference is not worth telling anybody about: the
// setting still took effect, it simply will not survive the reload.
export function writeSettings(settings: ViewSettings): void {
  try {
    window.localStorage.setItem(storageKey, JSON.stringify(settings))
  } catch {
    // Nothing to do and nothing to say.
  }
}
