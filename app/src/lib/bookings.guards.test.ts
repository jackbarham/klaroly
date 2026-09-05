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
  '../lib/bookings.ts',
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
  //
  // The ban has no exceptions now. It used to name bookingFixtures.ts, which
  // serialised a UTC instant and was entitled to; that file is gone and the
  // timestamps come from the API already serialised, so there is nothing left
  // for the exception to point at. A ban with no exceptions is a better test
  // than the one it replaces.
  it('never turns a date into a day key with toISOString', () => {
    // Matched as a method call, so toJSON, which is the same conversion under
    // another name, cannot be used to slip past this.
    expect(offences(/\.toISOString\s*\(|\.toJSON\s*\(/)).toEqual([])
  })

  // The stronger half of the same rule: if there is only one place that writes
  // the format string, there is only one place that can get it wrong.
  it('writes the day key format in exactly one place', () => {
    expect(offences(/'yyyy-MM-dd'/)).toHaveLength(1)
  })

})
