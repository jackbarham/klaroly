import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import * as tokenStorage from '@/lib/tokenStorage'
import { useAuthStore } from '@/stores/auth'
import { stubFetch } from '@/lib/testHelpers'

vi.mock('@/lib/platform', () => ({
  isNative: true,
  isIOS: true,
  isAndroid: false,
  isWeb: false,
  deviceName: () => 'iPhone or iPad',
}))

const fetchMock = stubFetch()

beforeEach(() => {
  setActivePinia(createPinia())
  tokenStorage.clear()
})

describe('auth store on native', () => {
  it('bootstrap with no stored token sets signed_out without a request', async () => {
    const store = useAuthStore()

    await store.bootstrap()

    expect(store.status).toBe('signed_out')
    expect(fetchMock).not.toHaveBeenCalled()
  })
})
