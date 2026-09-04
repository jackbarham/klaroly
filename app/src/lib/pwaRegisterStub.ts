// Test stand-in for the plugin's `virtual:pwa-register` module, which only
// exists inside a Vite build with the PWA plugin loaded. vitest.config.ts
// aliases the virtual module here so src/lib/updates.ts can be imported in a
// test, and updates.test.ts mocks it. Never imported by application code.

export function registerSW(): () => Promise<void> {
  return async () => {}
}
