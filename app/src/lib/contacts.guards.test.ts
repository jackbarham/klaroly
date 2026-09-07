import { describe, expect, it } from 'vitest'
import { offences, withoutTests } from '@/lib/sourceRules'

// Two rules about the contacts feature that nothing else can keep, checked by
// reading the source rather than by trusting a convention, the way
// boundary.test.ts, styleRules.test.ts and bookings.guards.test.ts already do.

const feature = withoutTests(import.meta.glob<string>([
  '../components/contacts/**/*.vue',
  '../components/contacts/**/*.ts',
  '../views/contacts/**/*.vue',
  '../views/contacts/**/*.ts',
  '../lib/contactList.ts',
  '../lib/contactView.ts',
  '../lib/contacts.ts',
  '../stores/contacts.ts',
  '../types/contacts.ts',
], { query: '?raw', import: 'default', eager: true }))

// Every component and view in the app, for the data-module rule, which is not
// about this feature's files but about all of them: src/lib/contacts.ts would
// be just as wrong imported from HomeView.
//
// Test files are left out of both, for the reason boundary.test.ts leaves them
// out: a test may reach for anything, and this file itself names the module it
// is banning. No live test uses that exit. ContactList.test.ts needs contacts
// to mount a list with, and it builds its own two rather than importing a
// payload, because a test asserting that a query matches nobody should say
// which people it is filtering.
const components = withoutTests(import.meta.glob<string>([
  '../components/**/*.vue',
  '../components/**/*.ts',
], { query: '?raw', import: 'default', eager: true }))

describe('the contacts feature', () => {
  it('is a set of files this test can actually see', () => {
    expect(feature.length).toBeGreaterThan(8)
    expect(components.length).toBeGreaterThan(20)
  })

  // src/lib/contacts.ts is the data module for this screen. A component
  // reaching past the store to it would go round the store's fetch-once rule
  // and its failed state, and boundary.test.ts bans it for every data module;
  // this says it again for the one this feature owns, because that rule finds
  // the layer by derivation and this names the file.
  //
  // It replaces the same pair written about src/lib/contactFixtures.ts, which
  // this screen read until it moved onto GET /api/contacts. The pair is
  // repointed rather than dropped: a guard whose subject has been deleted
  // finds nothing and passes for that reason.
  it('has no component importing the data module', () => {
    expect(offences(/from '@\/lib\/contacts'/, components)).toEqual([])
  })

  // Paired with the assertion above, so it cannot quietly stop being about
  // anything: something has to import the module, or a guard that finds
  // nothing is passing because the file is unreferenced rather than because
  // the rule is kept.
  it('has the store importing the data module, which is what makes the rule meaningful', () => {
    expect(offences(/from '@\/lib\/contacts'/, feature).join('\n')).toContain('src/stores/contacts.ts')
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
