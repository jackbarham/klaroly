import { ref } from 'vue'
import { registerSW } from 'virtual:pwa-register'

// Web target only. Registers the service worker and watches for a newer
// build. When one is waiting, updateAvailable turns true and the update bar
// in App.vue offers a reload; applyUpdate() tells the waiting worker to take
// over, and the plugin reloads the page once it has.
//
// This module is the one place in src/ that mentions the service worker.
// It is loaded through a dynamic import inside `if (__WEB_TARGET__)` in
// main.ts and App.vue, so the mobile bundle never contains it.

// The browser only re-checks the worker file on its own schedule, so ask
// explicitly: on an interval, and whenever the tab comes back into view.
const CHECK_INTERVAL_MS = 60 * 60 * 1000

export const updateAvailable = ref(false)

let update: () => Promise<void> = async () => {}

export function watchForUpdates(): void {
  update = registerSW({
    immediate: true,
    onNeedRefresh() {
      updateAvailable.value = true
    },
    onRegisteredSW(_url, registration) {
      if (registration === undefined) {
        return
      }

      const check = (): void => {
        // Offline or a failed fetch is nothing to act on.
        registration.update().catch(() => {})
      }

      setInterval(check, CHECK_INTERVAL_MS)

      document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
          check()
        }
      })
    },
  })
}

// Tells the waiting worker to take over, then reloads so the page runs the
// new build. The plugin reloads on its own only when the page was already
// controlled by the previous worker, which a first visit never is, so the
// reload is done here once the new worker reports itself activated.
export async function applyUpdate(): Promise<void> {
  const registration = await navigator.serviceWorker.getRegistration()
  const waiting = registration?.waiting ?? null

  await update()

  if (waiting !== null) {
    await activated(waiting)
  }

  window.location.reload()
}

function activated(worker: ServiceWorker): Promise<void> {
  return new Promise((resolve) => {
    if (worker.state === 'activated') {
      resolve()

      return
    }

    worker.addEventListener('statechange', () => {
      if (worker.state === 'activated') {
        resolve()
      }
    })
  })
}
