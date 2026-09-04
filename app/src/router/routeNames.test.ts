import { describe, expect, it } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { routes } from '@/router'

// The rule this file exists to keep: every route name written down anywhere in
// the app is a route that exists.
//
// Nothing else can check this. A route name is a string, so TypeScript sees
// nothing wrong with { name: 'dashboard' } after the dashboard has been
// renamed, and the mistake only appears when somebody reaches the screen that
// pushes it. Renaming a route is exactly the change that causes it.
//
// Both spellings are looked for: `name` for a router push or a RouterLink, and
// `routeName` for an entry in the navigation config.
//
// The value has to look like a route name, which in this app means lowercase
// letters and hyphens. That is what keeps a person's name in a fixture, such
// as name: 'Ellie Marsh', from being mistaken for a route. The cost is that a
// route named in any other style would not be checked, so do not name one in
// any other style.

const sources = import.meta.glob<string>([
  '../**/*.vue',
  '../**/*.ts',
], { query: '?raw', import: 'default', eager: true })

const files = Object.entries(sources).filter(([path]) => !path.endsWith('.test.ts'))

const pattern = /\b(?:route)?[Nn]ame: '([a-z][a-z-]*)'/g

const router = createRouter({
  history: createMemoryHistory(),
  routes,
})

interface Use {
  path: string
  name: string
}

const uses: Use[] = []

for (const [path, source] of files) {
  for (const match of source.matchAll(pattern)) {
    uses.push({ path: path.replace('../', 'src/'), name: match[1] })
  }
}

describe('every route name in the app', () => {
  it('is found in more than a handful of files', () => {
    expect(uses.length).toBeGreaterThan(20)
  })

  it('belongs to a route that exists', () => {
    const missing = uses
      .filter((use) => !router.hasRoute(use.name))
      .map((use) => `${use.path}: ${use.name}`)

    expect(missing).toEqual([])
  })
})
