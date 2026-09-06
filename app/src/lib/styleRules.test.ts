import { describe, expect, it } from 'vitest'

// The rules from CLAUDE.md that a component cannot be made to keep by
// TypeScript or ESLint, checked by reading the source of every component and
// view: no dark: variant, because the theme is two token layers and a
// component only touches the second; no Tailwind arbitrary value and no hex
// colour, because every value comes from a token.
//
// The .ts files under components are read as well as the .vue ones, because a
// class string does not stop being markup by being written in a module. Several
// already hold one: field.ts owns every control's edge classes, Sheet.vue
// exports its row classes, and barGlass.ts is the two bars' material. Globbing
// .vue alone left all of them outside the check while looking complete.

const sources = import.meta.glob<string>([
  '../components/**/*.vue',
  '../components/**/*.ts',
  '../views/**/*.vue',
], { query: '?raw', import: 'default', eager: true })

// This file is under that glob and names the patterns it bans, so it would
// report itself.
const files = Object.entries(sources).filter(([path]) => !path.endsWith('.test.ts'))

function offences(pattern: RegExp): string[] {
  const found: string[] = []

  for (const [path, source] of files) {
    for (const line of source.split('\n')) {
      if (pattern.test(line)) {
        found.push(`${path.replace('../', 'src/')}: ${line.trim()}`)
      }
    }
  }

  return found
}

describe('every component and view', () => {
  it('is a file this test can actually see', () => {
    expect(files.length).toBeGreaterThan(20)
  })

  it('never uses the dark: variant', () => {
    expect(offences(/\bdark:/)).toEqual([])
  })

  it('never uses a Tailwind arbitrary value', () => {
    // A utility followed by a bracket, such as p-[13px] or text-[#7047eb].
    expect(offences(/[a-z]-\[[^\]]+\]/)).toEqual([])
  })

  it('never names a colour by hex', () => {
    expect(offences(/#[0-9a-f]{3,8}\b/i)).toEqual([])
  })
})
