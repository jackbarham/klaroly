import { describe, expect, it } from 'vitest'
import { describeOffences, linesMatching, withoutTests } from '@/lib/sourceRules'

// The rule this file exists to keep: a component and a view display things
// and collect input. They do not talk to the API. Anything that needs the API
// goes through a Pinia store, which calls src/lib/auth.ts, which calls
// src/lib/api.ts, and that is the only file in the app that calls fetch.
//
// The one thing a screen may take from src/lib/api.ts is ApiError, because
// catching a failed request and showing the right message is a screen's job.
//
// This reads the source of every component and every view rather than
// trusting a convention, because the point of the rule is what happens when
// nobody is looking. Comments are read too: a screen's comment that mentions
// the module is not an import, and none does.

// Test files are left out: they may reach for anything, and this file itself
// mentions the module it is banning.
const files = withoutTests(import.meta.glob<string>([
  '../components/**/*.vue',
  '../components/**/*.ts',
  '../views/**/*.vue',
  '../views/**/*.ts',
], { query: '?raw', import: 'default', eager: true }))

const allowedImport = /^import \{ ApiError \} from '@\/lib\/api'$/

// The data layer, worked out rather than listed.
//
// This test used to name one file, src/lib/api.ts, which was the whole data
// layer at the time. It is not any more: src/lib/auth.ts and src/lib/bookings.ts
// sit under the stores and a component importing either would go round the
// store while the guard said nothing. Naming them here instead would only move
// the problem, because the third one would be written by somebody who had not
// read this file.
//
// So the layer is defined by what it does: a module in src/lib that uses the
// api client's verbs is a data module. src/lib/verification.ts deliberately is
// not one, and the distinction is real rather than a loophole: it goes through
// useAuthStore, so it sits above the stores as shared screen logic, and the
// only thing it takes from api.ts is ApiError. src/lib/form.ts is the same
// shape, and both stay importable by a screen for the same reason.
const libSources = import.meta.glob<string>('../lib/*.ts', { query: '?raw', import: 'default', eager: true })

// api itself is seeded rather than derived: it cannot import itself, and it is
// the layer by definition. Everything else earns its place by using api's
// verbs.
const dataModules = ['api', ...withoutTests(libSources)
  .filter(([, source]) => source
    .split('\n')
    .some((line) => /from '@\/lib\/api'/.test(line) && !allowedImport.test(line.trim())))
  .map(([path]) => path.replace('./', '').replace('.ts', ''))]

describe('components and views', () => {
  it('are files this test can actually see', () => {
    expect(files.length).toBeGreaterThan(20)
  })

  it('never import the API wrapper, except for the error type they catch', () => {
    const offending = linesMatching(/@\/lib\/api/, files, { skipComments: false })
      .filter((offence) => !allowedImport.test(offence.line))

    expect(describeOffences(offending)).toEqual([])
  })

  it('has found the data layer it is meant to be guarding', () => {
    // If this ever comes back empty the test below is guarding nothing, and
    // would pass for that reason rather than because the rule is kept.
    expect(dataModules).toContain('api')
    expect(dataModules).toContain('auth')
    expect(dataModules).toContain('bookings')
    expect(dataModules).toContain('enquiries')
    expect(dataModules).toContain('home')
    expect(dataModules).not.toContain('verification')
  })

  it('never import a data module directly, going round the store', () => {
    const offending = dataModules.flatMap((module) => linesMatching(
      new RegExp(`from '@/lib/${module}'`),
      files,
      { skipComments: false },
    )).filter((offence) => !allowedImport.test(offence.line))

    expect(describeOffences(offending)).toEqual([])
  })

  it('never call fetch themselves', () => {
    expect(describeOffences(linesMatching(/\bfetch\s*\(/, files, { skipComments: false }))).toEqual([])
  })
})
