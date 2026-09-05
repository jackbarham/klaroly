import { afterEach } from 'vitest'
import { createApp, h, type App, type Component } from 'vue'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter, type Router } from 'vue-router'
import { installKit } from '@/components/kit'
import i18n from '@/i18n'
import { routes } from '@/router'

// Mounting a component in a test, without a component testing library: the
// project has none and is not gaining one. This is the whole of it, and it is
// imported by test files only. The helpers for driving what was mounted are
// in testHelpers.ts.
//
// The router is built from the real routes array with a memory history and
// none of the guards, so a test can start at any URL, including a deep one,
// and every RouterLink resolves the same names the app uses.

export interface Mounted {
  app: App
  host: HTMLElement
  router: Router
}

export async function mount(component: Component, path: string, props: Record<string, unknown> = {}): Promise<Mounted> {
  const router = createRouter({
    history: createMemoryHistory(),
    routes,
  })

  await router.push(path)
  await router.isReady()

  const host = document.createElement('div')

  document.body.appendChild(host)

  const app = createApp({
    render: () => h(component, props),
  })

  app.use(createPinia())
  app.use(router)
  app.use(i18n)
  installKit(app)
  app.mount(host)

  return { app, host, router }
}

export function unmount(mounted: Mounted): void {
  mounted.app.unmount()
  mounted.host.remove()
}

// mount, with the unmount registered for after each test. Call it once at
// the top of a test file and use what it returns in place of mount.
export function mountWithCleanup(): typeof mount {
  let current: Mounted | null = null

  afterEach(() => {
    if (current) {
      unmount(current)
      current = null
    }
  })

  return async (component, path, props) => {
    current = await mount(component, path, props)

    return current
  }
}
