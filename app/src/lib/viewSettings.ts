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
 * A check for a field that may only hold one of a closed set of values.
 *
 * Built from the values rather than from the type, so the runtime list and the
 * union cannot drift: a fourth sort order added to the type without being
 * added here is a type error at the call site.
 *
 * Numbers as well as strings, because the home screen's preview count is
 * 3 | 4 | 6 | 'all', which is one closed set with two primitive types in it
 * rather than a reason for a second function.
 */
export function oneOf<T extends string | number>(...allowed: readonly T[]): Check<T> {
  return (value, fallback) => (allowed.includes(value as T) ? value as T : fallback)
}

export const boolean: Check<boolean> = (value, fallback) => (
  typeof value === 'boolean' ? value : fallback
)

/**
 * A check for a field holding every one of a closed set of strings, once each,
 * in some order.
 *
 * The home screen's block order is one: three keys, each exactly once. It joins
 * oneOf and boolean here rather than living beside that screen's settings
 * because it is the same category of thing, a runtime guard tied to a union,
 * and a second copy written by the fourth screen is the drift this file exists
 * to stop.
 *
 * **A permutation is not a list of allowed values and cannot be checked as
 * one.** A stored ['money', 'money', 'money'] is three known keys and would
 * pass a per-item oneOf, then render one block three times and lose the other
 * two. So the length, the membership and the absence of duplicates are all
 * checked, and anything short of all three falls back whole rather than being
 * repaired: a half-mended order is a screen nobody asked for.
 */
export function permutationOf<T extends string>(...values: readonly T[]): Check<T[]> {
  return (value, fallback) => {
    if (!Array.isArray(value) || value.length !== values.length) {
      return [...fallback]
    }

    const seen = new Set<unknown>(value)

    if (seen.size !== values.length || !values.every((allowed) => seen.has(allowed))) {
      return [...fallback]
    }

    return [...value] as T[]
  }
}

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
