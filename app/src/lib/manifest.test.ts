import { describe, expect, it } from 'vitest'

// The home screen install, held together by its paths.
//
// Every failure here is silent. A wrong icon path is a blank white square on
// the artist's home screen, a manifest that does not parse is ignored without
// a word, and neither reaches the console, the build or any other test: the
// files are static assets that nothing imports, so a typo in one is invisible
// to TypeScript, to ESLint and to Vite alike.
//
// It reads source rather than dist on purpose. A test that reads the build
// output either has to run a build of its own or passes vacuously when dist is
// missing, which is a guard satisfied by nothing being there. What makes the
// source equal what ships is the last assertion instead: vite-plugin-pwa
// generates a manifest by default, under the same name, written after the
// public directory is copied, so it overwrites this file in dist and the app
// ships the plugin's iconless one with nothing failing anywhere. That is a
// demonstrated regression rather than a hypothetical, which is why the line
// that prevents it is asserted here.

// Read through import.meta.glob, the same way the other source-reading guards
// read components, rather than through node:fs. src/ is typed with vite/client
// and no node types on purpose, and giving it node types so that one test can
// call readFileSync would open node:fs to every file in the app.
const sources = import.meta.glob<string>([
  '../../index.html',
  '../../vite.config.ts',
  '../../public/manifest.webmanifest',
], { query: '?raw', import: 'default', eager: true })

// The keys are the answer to "does this file exist". A glob is resolved
// against the filesystem, and without eager nothing is loaded to find out.
const publicFiles = Object.keys(import.meta.glob('../../public/*'))

interface ManifestIcon {
  src: string
  sizes: string
  type: string
  purpose: string
}

interface Manifest {
  icons: ManifestIcon[]
}

function read(path: string): string {
  const source = sources[`../../${path}`]

  if (source === undefined) {
    throw new Error(`${path} was not found. The glob above has to name it.`)
  }

  return source
}

// A manifest's icon src and a link's href are both absolute paths from the
// site root, and public/ is what the site root is built from.
function publicFileExists(urlPath: string): boolean {
  return publicFiles.includes(`../../public${urlPath}`)
}

function headLink(rel: string): string | null {
  const parsed = new DOMParser().parseFromString(read('index.html'), 'text/html')

  return parsed.querySelector(`link[rel="${rel}"]`)?.getAttribute('href') ?? null
}

const manifestSource = read('public/manifest.webmanifest')

describe('the web app manifest', () => {
  it('parses as JSON', () => {
    expect(() => JSON.parse(manifestSource)).not.toThrow()
  })

  // Without this, the icons assertion below passes on an empty array, which is
  // the state the manifest was in before this work and the exact thing it is
  // here to catch.
  it('names some icons', () => {
    const manifest = JSON.parse(manifestSource) as Manifest

    expect(manifest.icons.length).toBeGreaterThan(0)
  })

  it('names an icon file that exists for every icon', () => {
    const manifest = JSON.parse(manifestSource) as Manifest
    const missing = manifest.icons
      .map((icon) => icon.src)
      .filter((src) => !publicFileExists(src))

    expect(missing).toEqual([])
  })

  it('is linked from index.html, by a path that resolves to it', () => {
    const href = headLink('manifest')

    expect(href).not.toBeNull()
    expect(publicFileExists(href ?? '')).toBe(true)
  })
})

describe('the iOS home screen icon', () => {
  // iOS reads none of the manifest's icons for the home screen. This link is
  // the whole of it, so a wrong path here is the blank square.
  it('is linked from index.html, by a path that resolves to a file', () => {
    const href = headLink('apple-touch-icon')

    expect(href).not.toBeNull()
    expect(publicFileExists(href ?? '')).toBe(true)
  })
})

describe('vite-plugin-pwa', () => {
  // The day somebody re-enables generation, every assertion above still passes
  // and the built app carries a different manifest. This is the one that fails.
  it('still generates no manifest of its own', () => {
    expect(read('vite.config.ts')).toMatch(/^\s*manifest: false,$/m)
  })
})
