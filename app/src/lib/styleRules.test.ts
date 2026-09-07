import { describe, expect, it } from 'vitest'
import { offences, withoutTests } from '@/lib/sourceRules'

// The rules from CLAUDE.md that a component cannot be made to keep by
// TypeScript or ESLint, checked by reading the source of every component and
// view: no dark: variant, because the theme is two token layers and a
// component only touches the second; no Tailwind arbitrary value and no hex
// colour, because every value comes from a token.
//
// The .ts files under components and views are read as well as the .vue ones,
// because a class string does not stop being markup by being written in a
// module. Several already hold one: field.ts owns every control's edge classes
// and the segmented control, Sheet.vue exports its row classes, barGlass.ts is
// the two bars' material, and the kitchen sink's tokens.ts carries a class per
// token. Globbing .vue alone left all of them outside the check while looking
// complete.
//
// Comments are not skipped here: a hex colour is a hex colour wherever it is
// written, and the one file that explains these rules is this one, which is a
// test file and outside the glob.

const files = withoutTests(import.meta.glob<string>([
  '../components/**/*.vue',
  '../components/**/*.ts',
  '../views/**/*.vue',
  '../views/**/*.ts',
], { query: '?raw', import: 'default', eager: true }))

describe('every component and view', () => {
  it('is a file this test can actually see', () => {
    expect(files.length).toBeGreaterThan(20)
  })

  it('never uses the dark: variant', () => {
    expect(offences(/\bdark:/, files, { skipComments: false })).toEqual([])
  })

  it('never uses a Tailwind arbitrary value', () => {
    // A utility followed by a bracket, such as p-[13px] or text-[#7047eb].
    expect(offences(/[a-z]-\[[^\]]+\]/, files, { skipComments: false })).toEqual([])
  })

  it('never names a colour by hex', () => {
    expect(offences(/#[0-9a-f]{3,8}\b/i, files, { skipComments: false })).toEqual([])
  })
})
