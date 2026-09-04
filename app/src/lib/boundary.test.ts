import { describe, expect, it } from 'vitest'

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
// nobody is looking.

const sources = import.meta.glob<string>([
  '../components/**/*.vue',
  '../components/**/*.ts',
  '../views/**/*.vue',
  '../views/**/*.ts',
], { query: '?raw', import: 'default', eager: true })

// Test files are left out: they may reach for anything, and this file itself
// mentions the module it is banning.
const files = Object.entries(sources).filter(([path]) => !path.endsWith('.test.ts'))

const allowedImport = /^import \{ ApiError \} from '@\/lib\/api'$/

interface Offence {
  path: string
  line: string
}

function linesMatching(pattern: RegExp): Offence[] {
  const matches: Offence[] = []

  for (const [path, source] of files) {
    for (const line of source.split('\n')) {
      if (pattern.test(line)) {
        matches.push({ path, line: line.trim() })
      }
    }
  }

  return matches
}

// What a failure prints, so that it names the file rather than a count. The
// glob keys are relative to this file, so they are put back to project paths.
function describeOffences(offences: Offence[]): string[] {
  return offences.map((offence) => `${offence.path.replace('../', 'src/')}: ${offence.line}`)
}

describe('components and views', () => {
  it('are files this test can actually see', () => {
    expect(files.length).toBeGreaterThan(20)
  })

  it('never import the API wrapper, except for the error type they catch', () => {
    const offending = linesMatching(/@\/lib\/api/).filter((offence) => !allowedImport.test(offence.line))

    expect(describeOffences(offending)).toEqual([])
  })

  it('never call fetch themselves', () => {
    expect(describeOffences(linesMatching(/\bfetch\s*\(/))).toEqual([])
  })
})
