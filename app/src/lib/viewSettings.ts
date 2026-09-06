// How a screen's view settings are kept on the device, written once.
//
// A view setting says how one person likes to read their own list. It is not a
// property of the account and not a column: there is no request, nothing to
// synchronise, and one key holds one object so a fifth setting is a field
// rather than a fifth key.
//
// **This is the mechanism and not the values.** Contacts and Enquiries share
// no setting at all: one sorts by recency or alphabet, the other by staleness,
// stage or wedding date, and nothing else overlaps. What they share is the
// part that is easy to get subtly wrong, and the part where a second copy
// would drift without anybody noticing: the try around the ACCESSOR rather
// than only the parse, the object check after it, per-field validation rather
// than a cast, and a write that swallows its own failure.
//
// Each screen supplies its own type, defaults and field checks, so a value
// written by an older build cannot reach that screen's sort function.

// One field's check: whatever came out of storage, and what to use instead.
export type Check<T> = (value: unknown, fallback: T) => T

export type Checks<T> = { [K in keyof T]: Check<T[K]> }

/**
 * A check for a field that may only hold one of a closed set of strings.
 *
 * Built from the values rather than from the type, so the runtime list and the
 * union cannot drift: a fourth sort order added to the type without being
 * added here is a type error at the call site.
 */
export function oneOf<T extends string>(...allowed: readonly T[]): Check<T> {
  return (value, fallback) => (allowed.includes(value as T) ? value as T : fallback)
}

export const boolean: Check<boolean> = (value, fallback) => (
  typeof value === 'boolean' ? value : fallback
)

/**
 * The settings as they were left, or the defaults.
 *
 * Every read is wrapped, and not only the parse. A private window, a browser
 * set to block site data and a thumbnail renderer all make the accessor itself
 * throw on the way in, before there is any JSON to fail on, so a try around
 * JSON.parse alone would still take the screen down.
 */
export function readSettings<T extends object>(key: string, defaults: T, checks: Checks<T>): T {
  try {
    const stored = window.localStorage.getItem(key)

    if (stored === null) {
      return { ...defaults }
    }

    const parsed: unknown = JSON.parse(stored)

    if (typeof parsed !== 'object' || parsed === null) {
      return { ...defaults }
    }

    const values = parsed as Partial<Record<keyof T, unknown>>
    const settings = { ...defaults }

    for (const field of Object.keys(defaults) as (keyof T)[]) {
      settings[field] = checks[field](values[field], defaults[field])
    }

    return settings
  } catch {
    return { ...defaults }
  }
}

// Failing to remember a preference is not worth telling anybody about: the
// setting still took effect, it simply will not survive the reload.
export function writeSettings<T>(key: string, settings: T): void {
  try {
    window.localStorage.setItem(key, JSON.stringify(settings))
  } catch {
    // Nothing to do and nothing to say.
  }
}
