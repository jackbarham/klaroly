// Checks the built manifest against the files the build actually emitted.
//
// This runs as postbuild, so it runs inside `npm run build`, which is what
// Cloudflare's Workers Build runs on a push. A non-zero exit here fails the
// deploy rather than shipping a broken install.
//
// It is a build gate rather than a test because a test that reads dist has to
// run a build of its own or pass vacuously when dist is missing, which is a
// guard satisfied by nothing being there. Here dist exists by construction:
// the build just wrote it.
//
// What it is for: the Worker serves the assets with not_found_handling set to
// single-page-application, so a path the build never emitted comes back as
// index.html with a 200 rather than a 404. An icon that is missing is
// therefore an HTML document with an image's name, which iOS draws as a blank
// white square, with nothing in any log. src/lib/manifest.test.ts holds the
// source files together; this holds the build to them.

import { existsSync, readFileSync } from 'node:fs'
import { join } from 'node:path'

const dist = 'dist'
const problems = []

function distFileFor(urlPath) {
  // A manifest src and a link href are both absolute paths from the site root,
  // and dist is what the site root is served from.
  return join(dist, urlPath)
}

const manifestPath = join(dist, 'manifest.webmanifest')

if (!existsSync(manifestPath)) {
  problems.push(`${manifestPath} was not emitted by the build.`)
} else {
  let manifest

  try {
    manifest = JSON.parse(readFileSync(manifestPath, 'utf8'))
  } catch (error) {
    problems.push(`${manifestPath} is not valid JSON: ${error.message}`)
  }

  if (manifest !== undefined) {
    const icons = manifest.icons ?? []

    // Without this, the loop below passes on an empty array, which is the
    // state the plugin's generated manifest was in.
    if (icons.length === 0) {
      problems.push(`${manifestPath} names no icons. If a build plugin generated it, it has overwritten public/manifest.webmanifest.`)
    }

    for (const icon of icons) {
      if (!existsSync(distFileFor(icon.src))) {
        problems.push(`${manifestPath} names ${icon.src}, which the build did not emit.`)
      }
    }
  }
}

// The apple touch icon is checked too, because iOS reads none of the
// manifest's icons for the home screen: that one link is the whole of it, and
// it is named in index.html rather than in the manifest, so nothing above
// would notice it missing.
const indexPath = join(dist, 'index.html')

if (!existsSync(indexPath)) {
  problems.push(`${indexPath} was not emitted by the build.`)
} else {
  const html = readFileSync(indexPath, 'utf8')
  const links = [
    ['manifest', /<link[^>]+rel="manifest"[^>]+href="([^"]+)"/],
    ['apple-touch-icon', /<link[^>]+rel="apple-touch-icon"[^>]+href="([^"]+)"/],
  ]

  for (const [rel, pattern] of links) {
    const match = html.match(pattern)

    if (match === null) {
      problems.push(`${indexPath} has no ${rel} link.`)
    } else if (!existsSync(distFileFor(match[1]))) {
      problems.push(`${indexPath} links ${match[1]} as ${rel}, which the build did not emit.`)
    }
  }
}

if (problems.length > 0) {
  console.error('The built manifest does not match what the build emitted:\n')

  for (const problem of problems) {
    console.error(`  ${problem}`)
  }

  console.error('\nThe Worker answers a missing asset with index.html and a 200, so this would ship silently.')
  process.exit(1)
}

console.log('manifest: icons and links all resolve to emitted files')
