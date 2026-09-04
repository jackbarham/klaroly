import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { RegisterSWOptions } from 'virtual:pwa-register'

const registerSW = vi.fn()

vi.mock('virtual:pwa-register', () => ({ registerSW }))

// Imported after the mock so the module picks up the fake registerSW.
const { applyUpdate, updateAvailable, watchForUpdates } = await import('@/lib/updates')

function lastOptions(): RegisterSWOptions {
  return registerSW.mock.calls[0][0] as RegisterSWOptions
}

beforeEach(() => {
  vi.useFakeTimers()
  registerSW.mockReset()
  updateAvailable.value = false
})

afterEach(() => {
  vi.useRealTimers()
})

describe('watchForUpdates', () => {
  it('registers immediately and flags a waiting build', () => {
    registerSW.mockReturnValue(async () => {})

    watchForUpdates()

    expect(lastOptions().immediate).toBe(true)
    expect(updateAvailable.value).toBe(false)

    lastOptions().onNeedRefresh?.()

    expect(updateAvailable.value).toBe(true)
  })

  it('checks for a new worker hourly and when the tab becomes visible', () => {
    registerSW.mockReturnValue(async () => {})
    const registration = { update: vi.fn().mockResolvedValue(undefined) }

    watchForUpdates()
    lastOptions().onRegisteredSW?.('/sw.js', registration as unknown as ServiceWorkerRegistration)

    expect(registration.update).not.toHaveBeenCalled()

    vi.advanceTimersByTime(60 * 60 * 1000)

    expect(registration.update).toHaveBeenCalledTimes(1)

    vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('visible')
    document.dispatchEvent(new Event('visibilitychange'))

    expect(registration.update).toHaveBeenCalledTimes(2)
  })

  it('tells the waiting worker to take over, then reloads once it is active', async () => {
    const update = vi.fn().mockResolvedValue(undefined)
    registerSW.mockReturnValue(update)

    // A worker that is installed and waiting, which activates when told to.
    const listeners: Array<() => void> = []
    const waiting = {
      state: 'installed',
      addEventListener: (_type: string, listener: () => void) => listeners.push(listener),
    }
    vi.stubGlobal('navigator', {
      serviceWorker: { getRegistration: async () => ({ waiting }) },
    })
    const reload = vi.fn()
    vi.stubGlobal('location', { reload })

    watchForUpdates()
    const applied = applyUpdate()
    await vi.waitFor(() => expect(update).toHaveBeenCalledTimes(1))

    expect(reload).not.toHaveBeenCalled()

    waiting.state = 'activated'
    listeners.forEach(listener => listener())
    await applied

    expect(reload).toHaveBeenCalledTimes(1)

    vi.unstubAllGlobals()
  })
})
