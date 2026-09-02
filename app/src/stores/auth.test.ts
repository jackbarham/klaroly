import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { sampleMe } from '@/lib/auth.sample'
import { useAuthStore } from '@/stores/auth'

function jsonResponse(status: number, body: unknown = null): Response {
  return new Response(body === null ? '' : JSON.stringify(body), { status })
}

const fetchMock = vi.fn<typeof fetch>()

beforeEach(() => {
  setActivePinia(createPinia())
  fetchMock.mockReset()
  vi.stubGlobal('fetch', fetchMock)
  document.cookie = 'XSRF-TOKEN=token'
})

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('auth store on web', () => {
  it('bootstrap sets signed_in on a 200', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(200, { data: sampleMe }))
    const store = useAuthStore()

    await store.bootstrap()

    expect(store.status).toBe('signed_in')
    expect(store.me?.user.email).toBe('ellie@example.com')
    expect(store.isAuthenticated).toBe(true)
  })

  it('bootstrap sets signed_out on a 401', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(401, { message: 'Unauthenticated.' }))
    const store = useAuthStore()

    await store.bootstrap()

    expect(store.status).toBe('signed_out')
    expect(store.me).toBeNull()
    expect(store.notice).toBeNull()
  })

  it('bootstrap signs out with the no-membership notice on a 403', async () => {
    fetchMock
      .mockResolvedValueOnce(jsonResponse(403, { message: 'This login does not belong to any account.' }))
      .mockResolvedValueOnce(jsonResponse(204))
    const store = useAuthStore()

    await store.bootstrap()

    expect(store.status).toBe('signed_out')
    expect(store.notice).toBe('account.no_membership')
    expect(String(fetchMock.mock.calls[1][0])).toBe('http://api.test/logout')
  })

  it('bootstrap sets signed_out and does not throw on a network failure', async () => {
    fetchMock.mockRejectedValueOnce(new TypeError('Failed to fetch'))
    const store = useAuthStore()

    await expect(store.bootstrap()).resolves.toBeUndefined()
    expect(store.status).toBe('signed_out')
  })

  it('a 401 from any request signs the store out', async () => {
    fetchMock
      .mockResolvedValueOnce(jsonResponse(200, { data: sampleMe }))
      .mockResolvedValueOnce(jsonResponse(401, { message: 'Unauthenticated.' }))
    const store = useAuthStore()
    await store.bootstrap()

    await expect(store.refresh()).rejects.toMatchObject({ status: 401 })

    expect(store.status).toBe('signed_out')
    expect(store.me).toBeNull()
  })

  it('signOut clears state and leaves the signed-out notice even if the request fails', async () => {
    fetchMock
      .mockResolvedValueOnce(jsonResponse(200, { data: sampleMe }))
      .mockRejectedValueOnce(new TypeError('Failed to fetch'))
    const store = useAuthStore()
    await store.bootstrap()

    await store.signOut()

    expect(store.status).toBe('signed_out')
    expect(store.me).toBeNull()
    expect(store.takeNotice()).toBe('auth.signed_out')
    expect(store.takeNotice()).toBeNull()
  })
})
