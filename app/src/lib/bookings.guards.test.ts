import { describe, expect, it } from 'vitest'

// Two rules about the bookings feature that nothing else can keep, checked by
// reading the source rather than by trusting a convention, the way
// boundary.test.ts and styleRules.test.ts already do for the app at large.

const sources = import.meta.glob<string>([
  '../components/bookings/**/*.vue',
  '../components/bookings/**/*.ts',
  '../views/bookings/**/*.vue',
  '../views/bookings/**/*.ts',
  '../lib/monthGrid.ts',
  '../lib/dayMarks.ts',
  '../lib/bookingFixtures.ts',
  '../stores/bookings.ts',
  '../types/bookings.ts',
], { query: '?raw', import: 'default', eager: true })

const files = Object.entries(sources).filter(([path]) => !path.endsWith('.test.ts'))

function name(path: string): string {
  return path.replace('../', 'src/')
}

// These files explain the rules below in their own comments, and a guard that
// counts its own explanation is a guard that fails for the wrong reason. Only
// lines that are actually code are read.
function isComment(line: string): boolean {
  const trimmed = line.trim()

  return trimmed.startsWith('//')
    || trimmed.startsWith('*')
    || trimmed.startsWith('/*')
    || trimmed.startsWith('<!--')
}

function offences(pattern: RegExp, paths = files): string[] {
  const found: string[] = []

  for (const [path, source] of paths) {
    for (const line of source.split('\n')) {
      if (!isComment(line) && pattern.test(line)) {
        found.push(`${name(path)}: ${line.trim()}`)
      }
    }
  }

  return found
}

describe('the bookings feature', () => {
  it('is a set of files this test can actually see', () => {
    expect(files.length).toBeGreaterThan(8)
  })

  // The bug this exists to stop: toISOString is UTC, so using it to build a
  // day key files an evening event under the previous day for the eight
  // months the clocks are forward. format(d, 'yyyy-MM-dd') reads the local
  // calendar date and is the only way a date becomes a key here.
  it('never turns a date into a day key with toISOString', () => {
    // bookingFixtures.ts is the one exception, and a named one rather than a
    // pattern: it serialises last_touched_at, which is a UTC instant, and that
    // is exactly what the method is for. Everything else in the feature is
    // dealing in calendar dates.
    const others = files.filter(([path]) => !path.endsWith('bookingFixtures.ts'))

    // Matched as a method call, so toJSON, which is the same conversion under
    // another name, cannot be used to slip past this.
    expect(offences(/\.toISOString\s*\(|\.toJSON\s*\(/, others)).toEqual([])
  })

  // The stronger half of the same rule: if there is only one place that writes
  // the format string, there is only one place that can get it wrong.
  it('writes the day key format in exactly one place', () => {
    expect(offences(/'yyyy-MM-dd'/)).toHaveLength(1)
  })

  // The seam the API prompt swaps. A component that imports the fixtures is a
  // component that still needs editing on the day the request is real.
  it('never imports the fixtures into a component or a view', () => {
    const components = files.filter(([path]) => path.includes('/components/') || path.includes('/views/'))

    expect(offences(/bookingFixtures/, components)).toEqual([])
  })

  // Only the store may reach the fixtures, so there is one place the swap
  // happens and one place that holds the result.
  it('imports the fixtures into the store and nowhere else', () => {
    expect(offences(/from '@\/lib\/bookingFixtures'/)).toHaveLength(1)
  })
})
