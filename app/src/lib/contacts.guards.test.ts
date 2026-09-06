import { describe, expect, it } from 'vitest'

// Two rules about the contacts feature that nothing else can keep, checked by
// reading the source rather than by trusting a convention, the way
// boundary.test.ts, styleRules.test.ts and bookings.guards.test.ts already do.

const featureSources = import.meta.glob<string>([
  '../components/contacts/**/*.vue',
  '../components/contacts/**/*.ts',
  '../views/contacts/**/*.vue',
  '../views/contacts/**/*.ts',
  '../lib/contactList.ts',
  '../lib/contactView.ts',
  '../lib/contactFixtures.ts',
  '../stores/contacts.ts',
  '../types/contacts.ts',
], { query: '?raw', import: 'default', eager: true })

// Every component and view in the app, for the fixtures rule, which is not
// about this feature's files but about all of them: the fixtures would be just
// as wrong imported from HomeView.
const componentSources = import.meta.glob<string>([
  '../components/**/*.vue',
  '../components/**/*.ts',
], { query: '?raw', import: 'default', eager: true })

// Test files are left out of both, for the reason boundary.test.ts leaves them
// out: a test may reach for anything, and this file itself names the module it
// is banning. No live test uses that exit. ContactList.test.ts needs contacts
// to mount a list with, and it builds its own two rather than importing
// twenty-two, because a test asserting that a query matches nobody should say
// which people it is filtering.
const feature = Object.entries(featureSources).filter(([path]) => !path.endsWith('.test.ts'))
const components = Object.entries(componentSources).filter(([path]) => !path.endsWith('.test.ts'))

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

function offences(pattern: RegExp, paths: [string, string][]): string[] {
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

describe('the contacts feature', () => {
  it('is a set of files this test can actually see', () => {
    expect(feature.length).toBeGreaterThan(8)
    expect(components.length).toBeGreaterThan(20)
  })

  // The fixtures are a stand-in for an endpoint that does not exist yet. A
  // component reaching past the store to read them would still work today and
  // would break on the day the API lands, which is the worst possible time to
  // find out. The store is the only caller.
  it('has no component importing the fixtures', () => {
    expect(offences(/contactFixtures/, components)).toEqual([])
  })

  // Paired with the assertion above, so it cannot quietly stop being about
  // anything: something has to import the fixtures, or a guard that finds
  // nothing is passing because the file is unreferenced rather than because
  // the rule is kept.
  it('has the store importing the fixtures, which is what makes the rule meaningful', () => {
    expect(offences(/contactFixtures/, feature).join('\n')).toContain('src/stores/contacts.ts')
  })

  // The bug this exists to stop: toISOString is UTC, so using it to build a
  // day key files an evening event under the previous day for the eight months
  // the clocks are forward. format(d, 'yyyy-MM-dd') reads the local calendar
  // date, and dayKey in src/lib/monthGrid.ts is the one place that happens.
  it('never turns a date into a day key with toISOString', () => {
    // Matched as a method call, so toJSON, which is the same conversion under
    // another name, cannot be used to slip past this.
    expect(offences(/\.toISOString\s*\(|\.toJSON\s*\(/, feature)).toEqual([])
  })

  // The stronger half of the same rule. This feature writes the format string
  // nowhere at all: it imports dayKey from the calendar's arithmetic, which is
  // already the single place the app turns a date into a key.
  it('never writes the day key format itself', () => {
    expect(offences(/'yyyy-MM-dd'/, feature)).toEqual([])
  })
})
