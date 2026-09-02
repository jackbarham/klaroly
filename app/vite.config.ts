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
    // The service worker exists only on the web target. The plugin injects
    // its own registration script into index.html, so nothing in src/ refers
    // to it and there is nothing to strip from the mobile build.
    plugins.push(
      VitePWA({
        registerType: 'autoUpdate',
        injectRegister: 'script-defer',
        manifest: {
          name: 'Klaroly',
          short_name: 'Klaroly',
          start_url: '/',
          display: 'standalone',
          // Keep these in step with --color-surface and --color-brand in
          // src/assets/app.css. A manifest cannot read CSS variables.
          background_color: '#faf9f7',
          theme_color: '#7a3e8c',
          // Icons are added when the brand assets exist.
          icons: [],
        },
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
      port: 5173,
      strictPort: true,
    },
  }
})
