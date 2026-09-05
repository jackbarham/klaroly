import { describe, expect, it } from 'vitest'

// The rules from CLAUDE.md that a component cannot be made to keep by
// TypeScript or ESLint, checked by reading the source of every component and
// view: no dark: variant, because the theme is two token layers and a
// component only touches the second; no Tailwind arbitrary value and no hex
// colour, because every value comes from a token.

const sources = import.meta.glob<string>([
  '../components/**/*.vue',
  '../views/**/*.vue',
], { query: '?raw', import: 'default', eager: true })

const files = Object.entries(sources)

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
