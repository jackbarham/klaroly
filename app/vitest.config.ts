import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'

// Separate from vite.config.ts because that file insists on VITE_TARGET being
// set by an npm script, and the test run should not have to pick a target:
// tests that need the native branch mock src/lib/platform.ts instead.
export default defineConfig({
  plugins: [vue()],
  define: {
    __WEB_TARGET__: 'true',
  },
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  test: {
    environment: 'happy-dom',
    include: ['src/**/*.test.ts'],
    env: {
      VITE_API_URL: 'http://api.test',
    },
  },
})
