import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError, api, onUnauthenticated } from '@/lib/api'

// The web branch: platform.ts is not mocked here, and without VITE_TARGET
// set the app thinks it is the web target.

function jsonResponse(status: number, body: unknown = null): Response {
  return new Response(body === null ? '' : JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  })
}

function clearCookies(): void {
  document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT'
}

const fetchMock = vi.fn<typeof fetch>()

beforeEach(() => {
  fetchMock.mockReset()
  vi.stubGlobal('fetch', fetchMock)
  clearCookies()
  onUnauthenticated(() => {})
})

afterEach(() => {
  vi.unstubAllGlobals()
})

function requestAt(index: number): { url: string, init: RequestInit } {
  const call = fetchMock.mock.calls[index]

  return { url: String(call[0]), init: call[1] ?? {} }
}

function headerOf(init: RequestInit, name: string): string | null {
  return new Headers(init.headers).get(name)
}

describe('api on web', () => {
  it('sends Accept: application/json and includes credentials on a GET', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(200, { ok: true }))

    await api.get('/api/me')

    const { url, init } = requestAt(0)
    expect(url).toBe('http://api.test/api/me')
    expect(headerOf(init, 'Accept')).toBe('application/json')
    expect(init.credentials).toBe('include')
    expect(headerOf(init, 'Authorization')).toBeNull()
  })

  it('sends the URL-decoded XSRF cookie on a non-GET', async () => {
    document.cookie = `XSRF-TOKEN=${encodeURIComponent('abc=123')}`
    fetchMock.mockResolvedValueOnce(jsonResponse(200, {}))

    await api.post('/login', { email: 'a@example.com' })

    expect(fetchMock).toHaveBeenCalledTimes(1)
    const { init } = requestAt(0)
    expect(headerOf(init, 'X-XSRF-TOKEN')).toBe('abc=123')
    expect(headerOf(init, 'Content-Type')).toBe('application/json')
    expect(init.method).toBe('POST')
  })

  it('fetches the CSRF cookie before a non-GET when the cookie is absent', async () => {
    fetchMock
      .mockImplementationOnce(async () => {
        document.cookie = 'XSRF-TOKEN=fresh'

        return jsonResponse(204)
      })
      .mockResolvedValueOnce(jsonResponse(200, {}))

    await api.post('/login', {})

    expect(fetchMock).toHaveBeenCalledTimes(2)
    expect(requestAt(0).url).toBe('http://api.test/sanctum/csrf-cookie')
    expect(requestAt(0).init.credentials).toBe('include')
    expect(requestAt(1).url).toBe('http://api.test/login')
    expect(headerOf(requestAt(1).init, 'X-XSRF-TOKEN')).toBe('fresh')
  })

  it('does not fetch the CSRF cookie before a GET', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(200, {}))

    await api.get('/api/me')

    expect(fetchMock).toHaveBeenCalledTimes(1)
  })

  it('refetches the cookie and retries exactly once on a 419', async () => {
    document.cookie = 'XSRF-TOKEN=stale'
    fetchMock
      .mockResolvedValueOnce(jsonResponse(419, { message: 'CSRF token mismatch.' }))
      .mockImplementationOnce(async () => {
        document.cookie = 'XSRF-TOKEN=renewed'

        return jsonResponse(204)
      })
      .mockResolvedValueOnce(jsonResponse(200, { done: true }))

    const result = await api.post<{ done: boolean }>('/logout')

    expect(result).toEqual({ done: true })
    expect(fetchMock).toHaveBeenCalledTimes(3)
    expect(requestAt(1).url).toBe('http://api.test/sanctum/csrf-cookie')
    expect(requestAt(2).url).toBe('http://api.test/logout')
    expect(headerOf(requestAt(2).init, 'X-XSRF-TOKEN')).toBe('renewed')
  })

  it('throws when the retry after a 419 is a 419 as well', async () => {
    document.cookie = 'XSRF-TOKEN=stale'
    fetchMock
      .mockResolvedValueOnce(jsonResponse(419, { message: 'CSRF token mismatch.' }))
      .mockResolvedValueOnce(jsonResponse(204))
      .mockResolvedValueOnce(jsonResponse(419, { message: 'CSRF token mismatch.' }))

    await expect(api.post('/logout')).rejects.toMatchObject({ status: 419 })
    expect(fetchMock).toHaveBeenCalledTimes(3)
  })

  it('does not retry a 419 on a GET', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(419, {}))

    await expect(api.get('/api/me')).rejects.toMatchObject({ status: 419 })
    expect(fetchMock).toHaveBeenCalledTimes(1)
  })

  it('resolves a 204 to null', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(204))

    await expect(api.get('/api/thing')).resolves.toBeNull()
  })

  it('throws an ApiError with the parsed body and maps validation errors', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(422, {
      message: 'The given data was invalid.',
      errors: { email: ['First email message', 'Second'], password: ['Too short'] },
    }))

    const failure = await api.get('/api/thing').catch((error: unknown) => error)

    expect(failure).toBeInstanceOf(ApiError)
    const error = failure as ApiError
    expect(error.status).toBe(422)
    expect(error.body).toMatchObject({ message: 'The given data was invalid.' })
    expect(error.validationErrors()).toEqual({ email: 'First email message', password: 'Too short' })
  })

  it('gives an empty validation map for anything but a 422', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(500, { message: 'Server error' }))

    const failure = await api.get('/api/thing').catch((error: unknown) => error)

    expect(failure).toBeInstanceOf(ApiError)
    expect((failure as ApiError).validationErrors()).toEqual({})
  })

  it('calls the onUnauthenticated handler before throwing on a 401', async () => {
    const handler = vi.fn()
    onUnauthenticated(handler)
    fetchMock.mockResolvedValueOnce(jsonResponse(401, { message: 'Unauthenticated.' }))

    await expect(api.get('/api/me')).rejects.toMatchObject({ status: 401 })
    expect(handler).toHaveBeenCalledTimes(1)
  })

  it('does not call the handler on other failures', async () => {
    const handler = vi.fn()
    onUnauthenticated(handler)
    fetchMock.mockResolvedValueOnce(jsonResponse(403, { message: 'No.' }))

    await expect(api.get('/api/me')).rejects.toMatchObject({ status: 403 })
    expect(handler).not.toHaveBeenCalled()
  })
})
