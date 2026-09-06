import { describe, expect, it } from 'vitest'

// Two rules about the enquiries feature that nothing else can keep, checked by
// reading the source rather than by trusting a convention, the way
// boundary.test.ts, styleRules.test.ts and the two guard files beside this one
// already do.

const featureSources = import.meta.glob<string>([
  '../components/enquiries/**/*.vue',
  '../components/enquiries/**/*.ts',
  '../views/enquiries/**/*.vue',
  '../views/enquiries/**/*.ts',
], { query: '?raw', import: 'default', eager: true })

const files = Object.entries(featureSources).filter(([path]) => !path.endsWith('.test.ts'))

// Comments are skipped, for the reason boundary.test.ts gives about itself:
// the comment on EnquiryList.vue explaining why the list is not a listbox
// necessarily says the words "role=option", and a rule that failed on its own
// explanation would be a rule nobody could document.
function isComment(line: string): boolean {
  const trimmed = line.trim()

  return trimmed.startsWith('//') || trimmed.startsWith('*') || trimmed.startsWith('/*')
}

function offences(pattern: RegExp): string[] {
  const found: string[] = []

  for (const [path, source] of files) {
    for (const line of source.split('\n')) {
      if (!isComment(line) && pattern.test(line)) {
        found.push(`${path.replace('../', 'src/')}: ${line.trim()}`)
      }
    }
  }

  return found
}

describe('the enquiries feature', () => {
  it('is a set of files this test can actually see', () => {
    expect(files.length).toBeGreaterThan(5)
  })

  /**
   * The one thing not to copy from contacts.
   *
   * The stage pill is a control inside the row, and an ARIA option may not
   * contain one: a button inside an option is either flattened out of the
   * accessibility tree or stops its parent being an option, and the pattern
   * could not reach the pill anyway, because aria-activedescendant moves a
   * virtual cursor and a control needs real focus.
   *
   * The next person building a list here will open ContactList.vue first, so
   * this is checked rather than left to the comment that explains it.
   */
  it('never makes the list a listbox', () => {
    expect(offences(/role="(listbox|option)"/)).toEqual([])
    expect(offences(/aria-activedescendant/)).toEqual([])
    expect(offences(/role="combobox"/)).toEqual([])
  })

  // Paired with the rule above, so it cannot pass on a feature that renders no
  // roles at all.
  it('does make it a list', () => {
    expect(offences(/role="list"/).length).toBeGreaterThan(0)
  })

  /**
   * The gone-quiet threshold is one number, read once, on the server. A screen
   * that worked it out for itself could disagree with the home screen's
   * attention block about which enquiries have gone quiet, and neither would
   * be obviously wrong.
   */
  it('never computes a cold threshold of its own', () => {
    expect(offences(/cold_enquiry_days|coldDays|COLD_DAYS/)).toEqual([])
  })
})
