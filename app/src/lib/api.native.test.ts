import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '@/lib/api'
import * as tokenStorage from '@/lib/tokenStorage'

// The native branch. Mocking platform.ts is the one approved way to test it.
vi.mock('@/lib/platform', () => ({
  isNative: true,
  isIOS: false,
  isAndroid: false,
  isWeb: false,
  deviceName: () => 'Mobile',
}))

const fetchMock = vi.fn<typeof fetch>()

beforeEach(() => {
  fetchMock.mockReset()
  vi.stubGlobal('fetch', fetchMock)
  tokenStorage.clear()
})

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('api on native', () => {
  it('sends the bearer token from tokenStorage, omits credentials and never fetches the CSRF cookie', async () => {
    tokenStorage.set('secret-token')
    fetchMock.mockResolvedValueOnce(new Response('{}', { status: 200 }))

    await api.post('/api/auth/token', { email: 'a@example.com' })

    expect(fetchMock).toHaveBeenCalledTimes(1)
    const [url, init] = fetchMock.mock.calls[0]
    const headers = new Headers(init?.headers)
    expect(String(url)).toBe('http://api.test/api/auth/token')
    expect(headers.get('Accept')).toBe('application/json')
    expect(headers.get('Authorization')).toBe('Bearer secret-token')
    expect(headers.get('X-XSRF-TOKEN')).toBeNull()
    expect(init?.credentials).toBe('omit')
  })

  it('sends no Authorization header when there is no token', async () => {
    fetchMock.mockResolvedValueOnce(new Response('{}', { status: 200 }))

    await api.get('/api/me')

    const headers = new Headers(fetchMock.mock.calls[0][1]?.headers)
    expect(headers.get('Authorization')).toBeNull()
  })

  it('does not retry a 419', async () => {
    fetchMock.mockResolvedValueOnce(new Response('{}', { status: 419 }))

    await expect(api.post('/api/thing')).rejects.toMatchObject({ status: 419 })
    expect(fetchMock).toHaveBeenCalledTimes(1)
  })
})
