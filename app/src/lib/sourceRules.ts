// The scaffold the source-reading tests share, written once. Imported by test
// files only.
//
// Five tests read the app's own source rather than run it: the boundary rule,
// the style rules and the three feature guards. Each of them globs a set of
// files, drops the test files from it, walks the lines, skips the comments
// that explain the very rule being checked, and names an offending line by its
// project path. Written five times, one copy had already drifted: the
// enquiries guard skipped fewer kinds of comment than the other two, so an
// HTML comment mentioning a banned role failed there and passed beside it.

export interface Offence {
  path: string
  line: string
}

// A glob's entries as [path, source] pairs, without the test files: a test
// may reach for anything, and a guard file itself names what it is banning.
export function withoutTests(sources: Record<string, string>): [string, string][] {
  return Object.entries(sources).filter(([path]) => !path.endsWith('.test.ts'))
}

// The glob keys are relative to the test file, so they are put back to
// project paths for a failure that names the file rather than a count.
export function projectPath(path: string): string {
  return path.replace('../', 'src/')
}

// A guard that counts its own explanation is a guard that fails for the wrong
// reason, so a rule may ask for the comment lines to be skipped: a line
// comment, a docblock line, the start of a block comment or an HTML comment.
export function isComment(line: string): boolean {
  const trimmed = line.trim()

  return trimmed.startsWith('//')
    || trimmed.startsWith('*')
    || trimmed.startsWith('/*')
    || trimmed.startsWith('<!--')
}

/**
 * Every line in the files matching the pattern, as the path it was found in
 * and the line itself.
 */
export function linesMatching(
  pattern: RegExp,
  files: [string, string][],
  { skipComments = true }: { skipComments?: boolean } = {},
): Offence[] {
  const found: Offence[] = []

  for (const [path, source] of files) {
    for (const line of source.split('\n')) {
      if ((!skipComments || !isComment(line)) && pattern.test(line)) {
        found.push({ path, line: line.trim() })
      }
    }
  }

  return found
}

// What a failure prints: one string per offending line, naming the file.
export function describeOffences(found: Offence[]): string[] {
  return found.map((offence) => `${projectPath(offence.path)}: ${offence.line}`)
}

export function offences(
  pattern: RegExp,
  files: [string, string][],
  options: { skipComments?: boolean } = {},
): string[] {
  return describeOffences(linesMatching(pattern, files, options))
}
