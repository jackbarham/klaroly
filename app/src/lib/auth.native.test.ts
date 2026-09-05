import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import * as auth from '@/lib/auth'
import { sampleMe } from '@/lib/auth.sample'
import * as tokenStorage from '@/lib/tokenStorage'
import { jsonResponse } from '@/lib/testHelpers'

// The native branch of src/lib/auth.ts. Mocking platform.ts is the one
// approved way to test it.
vi.mock('@/lib/platform', () => ({
  isNative: true,
  isIOS: false,
  isAndroid: true,
  isWeb: false,
  deviceName: () => 'Android',
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

function sentBody(index: number): Record<string, unknown> {
  return JSON.parse(String(fetchMock.mock.calls[index][1]?.body))
}

function sentUrl(index: number): string {
  return String(fetchMock.mock.calls[index][0])
}

describe('auth on native', () => {
  it('signIn posts /api/auth/token with device_name, stores the token and returns me', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(200, { token: 'plain', expires_at: null, me: sampleMe }))

    const me = await auth.signIn('ellie@example.com', 'password', true)

    expect(fetchMock).toHaveBeenCalledTimes(1)
    expect(sentUrl(0)).toBe('http://api.test/api/auth/token')
    expect(sentBody(0)).toEqual({ email: 'ellie@example.com', password: 'password', device_name: 'Android' })
    expect(tokenStorage.get()).toBe('plain')
    expect(me.user.email).toBe('ellie@example.com')
    expect(me.user.notification_preferences).toEqual({})
  })

  it('register posts /api/auth/register with device_name and the confirmation, stores the token and returns me', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(201, { token: 'plain', expires_at: null, me: sampleMe }))

    const me = await auth.register({
      business_name: 'Ellie Marsh Makeup',
      name: 'Ellie',
      email: 'ellie@example.com',
      password: 'correct-horse-battery',
      marketing_consent: false,
    })

    expect(fetchMock).toHaveBeenCalledTimes(1)
    expect(sentUrl(0)).toBe('http://api.test/api/auth/register')
    expect(sentBody(0)).toMatchObject({
      password: 'correct-horse-battery',
      password_confirmation: 'correct-horse-battery',
      device_name: 'Android',
    })
    expect(tokenStorage.get()).toBe('plain')
    expect(me.user.notification_preferences).toEqual({})
  })

  it('signOut deletes /api/auth/token and clears the token', async () => {
    tokenStorage.set('plain')
    fetchMock.mockResolvedValueOnce(jsonResponse(204))

    await auth.signOut()

    expect(sentUrl(0)).toBe('http://api.test/api/auth/token')
    expect(fetchMock.mock.calls[0][1]?.method).toBe('DELETE')
    expect(tokenStorage.get()).toBeNull()
  })

  it('signOut clears the token even when the request fails', async () => {
    tokenStorage.set('plain')
    fetchMock.mockRejectedValueOnce(new TypeError('Failed to fetch'))

    await expect(auth.signOut()).rejects.toBeInstanceOf(TypeError)
    expect(tokenStorage.get()).toBeNull()
  })

  it('the other functions use the /api/auth twins', async () => {
    fetchMock
      .mockResolvedValueOnce(jsonResponse(200, { message: 'sent' }))
      .mockResolvedValueOnce(jsonResponse(200, { message: 'reset' }))
      .mockResolvedValueOnce(jsonResponse(202, { status: 'verification-link-sent' }))

    await auth.forgotPassword('ellie@example.com')
    await auth.resetPassword('tok', 'ellie@example.com', 'new-password-here')
    await auth.resendVerification()

    expect(sentUrl(0)).toBe('http://api.test/api/auth/forgot-password')
    expect(sentUrl(1)).toBe('http://api.test/api/auth/reset-password')
    expect(sentUrl(2)).toBe('http://api.test/api/auth/email/verification-notification')
  })
})
