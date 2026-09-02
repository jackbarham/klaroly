import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { sampleMe } from '@/lib/auth.sample'
import router, { destinationAfterSignIn } from '@/router'
import { useAuthStore } from '@/stores/auth'

function jsonResponse(status: number, body: unknown = null): Response {
  return new Response(body === null ? '' : JSON.stringify(body), { status })
}

const fetchMock = vi.fn<typeof fetch>()

// One pinia for the whole file: the router bootstraps the store once, on
// its first navigation, and the tests below rely on that order.
setActivePinia(createPinia())

beforeEach(() => {
  vi.stubGlobal('fetch', fetchMock)
})

describe('router guard', () => {
  it('waits for bootstrap once and sends a signed-out visitor to login with a redirect', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(401, { message: 'Unauthenticated.' }))

    await router.push('/billing')

    expect(router.currentRoute.value.name).toBe('login')
    expect(router.currentRoute.value.query.redirect).toBe('/billing')
    expect(fetchMock).toHaveBeenCalledTimes(1)
    expect(String(fetchMock.mock.calls[0][0])).toBe('http://api.test/api/me')
  })

  it('does not bootstrap again on a later navigation', async () => {
    await router.push('/register')

    expect(router.currentRoute.value.name).toBe('register')
    expect(fetchMock).toHaveBeenCalledTimes(1)
  })

  it('sends a signed-in visitor away from guest-only routes', async () => {
    const store = useAuthStore()
    store.me = sampleMe
    store.status = 'signed_in'

    await router.push('/login')

    expect(router.currentRoute.value.name).toBe('dashboard')
    expect(fetchMock).toHaveBeenCalledTimes(1)
  })
})

describe('destinationAfterSignIn', () => {
  it('follows a relative path', () => {
    expect(destinationAfterSignIn('/billing')).toBe('/billing')
    expect(destinationAfterSignIn('/bookings?page=2')).toBe('/bookings?page=2')
  })

  it('refuses a protocol-relative or absolute URL and anything else', () => {
    expect(destinationAfterSignIn('//evil.example')).toBe('/')
    expect(destinationAfterSignIn('https://evil.example')).toBe('/')
    expect(destinationAfterSignIn('billing')).toBe('/')
    expect(destinationAfterSignIn(undefined)).toBe('/')
    expect(destinationAfterSignIn(['/a'])).toBe('/')
  })
})
