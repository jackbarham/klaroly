/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_API_URL: string
  readonly VITE_TARGET: 'web' | 'mobile'
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}

// Compile-time constant defined in vite.config.ts. True on the web build,
// false on the mobile build.
declare const __WEB_TARGET__: boolean
