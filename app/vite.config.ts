import { fileURLToPath, URL } from 'node:url'
import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { VitePWA } from 'vite-plugin-pwa'

// One codebase, two build targets, chosen by VITE_TARGET:
//
//   web     service worker on, billing routes included
//   mobile  service worker off, billing routes excluded
//
// The npm scripts in package.json set VITE_TARGET. It is not read from .env,
// so that a build can never pick up the wrong target by accident.
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), 'VITE_')
  const target = env.VITE_TARGET

  if (target !== 'web' && target !== 'mobile') {
    throw new Error('VITE_TARGET must be "web" or "mobile". Use the npm scripts in package.json.')
  }

  if (!env.VITE_API_URL) {
    throw new Error('VITE_API_URL is not set. Copy .env.example to .env.')
  }

  const isWebTarget = target === 'web'

  const plugins = [vue(), tailwindcss()]

  if (isWebTarget) {
    // The service worker exists only on the web target. It is registered by
    // src/lib/updates.ts, which main.ts loads inside `if (__WEB_TARGET__)`,
    // so the mobile bundle never contains it. In prompt mode a newer worker
    // waits until the person chooses to reload from the update bar.
    plugins.push(
      VitePWA({
        registerType: 'prompt',
        injectRegister: null,
        // The manifest is public/manifest.webmanifest, written by hand, and
        // index.html links it. This is false so that the plugin neither
        // generates one nor injects a second link: both files want the name
        // manifest.webmanifest in dist, the plugin writes after the public
        // directory is copied, and it wins silently. Turning generation back
        // on ships the icons and colours below instead of the ones in the
        // file, with nothing failing anywhere, so src/lib/manifest.test.ts
        // asserts this line.
        //
        // Both colours in that file are #ffffff, which is --surface in
        // src/assets/app.css. The theme colour was the accent purple here and
        // is not the accent's to hold: it paints the status bar strip, which
        // sits directly above the top bar's white glass, so the accent put a
        // purple band above it. The accent belongs in the icon.
        manifest: false,
      }),
    )
  }

  return {
    plugins,
    define: {
      // Compile-time constant. On the mobile target it is replaced with the
      // literal false, so an `if (__WEB_TARGET__)` block is dead code and is
      // removed from the bundle along with anything it dynamically imports.
      __WEB_TARGET__: JSON.stringify(isWebTarget),
    },
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url)),
      },
    },
    server: {
      // Herd proxies http://app.klaroly.test to 127.0.0.1:5173, so bind to
      // that exact address (not localhost, which can mean IPv6 only) and
      // accept the proxied hostname, which Vite would otherwise refuse.
      host: '127.0.0.1',
      port: 5173,
      strictPort: true,
      allowedHosts: ['app.klaroly.test'],
    },
  }
})
