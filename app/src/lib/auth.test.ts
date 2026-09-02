import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import * as auth from '@/lib/auth'
import { sampleMe } from '@/lib/auth.sample'

// The web branch of src/lib/auth.ts.

function jsonResponse(status: number, body: unknown = null): Response {
  return new Response(body === null ? '' : JSON.stringify(body), { status })
}

const fetchMock = vi.fn<typeof fetch>()

beforeEach(() => {
  fetchMock.mockReset()
  vi.stubGlobal('fetch', fetchMock)
  document.cookie = 'XSRF-TOKEN=token'
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

describe('auth on web', () => {
  it('signIn posts /login then gets /api/me', async () => {
    fetchMock
      .mockResolvedValueOnce(jsonResponse(200, { two_factor: false }))
      .mockResolvedValueOnce(jsonResponse(200, { data: sampleMe }))

    const me = await auth.signIn('ellie@example.com', 'password', true)

    expect(sentUrl(0)).toBe('http://api.test/login')
    expect(sentBody(0)).toEqual({ email: 'ellie@example.com', password: 'password', remember: true })
    expect(sentUrl(1)).toBe('http://api.test/api/me')
    expect(me.account.name).toBe('Ellie Marsh Makeup')
  })

  it('register posts /register with password_confirmation equal to password, then gets /api/me', async () => {
    fetchMock
      .mockResolvedValueOnce(jsonResponse(201))
      .mockResolvedValueOnce(jsonResponse(200, { data: sampleMe }))

    await auth.register({
      business_name: 'Ellie Marsh Makeup',
      name: 'Ellie',
      email: 'ellie@example.com',
      username: 'ElliEMarsh',
      password: 'correct-horse-battery',
      marketing_consent: false,
    })

    expect(sentUrl(0)).toBe('http://api.test/register')
    expect(sentBody(0)).toMatchObject({
      password: 'correct-horse-battery',
      password_confirmation: 'correct-horse-battery',
      username: 'elliemarsh',
      marketing_consent: false,
    })
    expect(sentBody(0)).not.toHaveProperty('device_name')
    expect(sentUrl(1)).toBe('http://api.test/api/me')
  })

  it('register leaves username out when it is empty', async () => {
    fetchMock
      .mockResolvedValueOnce(jsonResponse(201))
      .mockResolvedValueOnce(jsonResponse(200, { data: sampleMe }))

    await auth.register({
      business_name: 'Ellie Marsh Makeup',
      name: 'Ellie',
      email: 'ellie@example.com',
      username: '',
      password: 'correct-horse-battery',
      marketing_consent: true,
    })

    expect(sentBody(0)).not.toHaveProperty('username')
  })

  it('signOut posts /logout', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(204))

    await auth.signOut()

    expect(sentUrl(0)).toBe('http://api.test/logout')
  })

  it('fetchMe turns an empty-array notification_preferences into an empty object', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(200, { data: sampleMe }))

    const me = await auth.fetchMe()

    expect(me.user.notification_preferences).toEqual({})
    expect(Array.isArray(me.user.notification_preferences)).toBe(false)
  })

  it('fetchMe keeps a populated notification_preferences map', async () => {
    const populated = { ...sampleMe, user: { ...sampleMe.user, notification_preferences: { sms: true } } }
    fetchMock.mockResolvedValueOnce(jsonResponse(200, { data: populated }))

    const me = await auth.fetchMe()

    expect(me.user.notification_preferences).toEqual({ sms: true })
  })

  it('forgotPassword, resetPassword and resendVerification use the Fortify routes', async () => {
    fetchMock
      .mockResolvedValueOnce(jsonResponse(200, { message: 'sent' }))
      .mockResolvedValueOnce(jsonResponse(200, { message: 'reset' }))
      .mockResolvedValueOnce(jsonResponse(202, { status: 'verification-link-sent' }))
      .mockResolvedValueOnce(jsonResponse(204))

    await auth.forgotPassword('ellie@example.com')
    await auth.resetPassword('tok', 'ellie@example.com', 'new-password-here')
    const sent = await auth.resendVerification()
    const alreadyVerified = await auth.resendVerification()

    expect(sentUrl(0)).toBe('http://api.test/forgot-password')
    expect(sentUrl(1)).toBe('http://api.test/reset-password')
    expect(sentBody(1)).toEqual({
      token: 'tok',
      email: 'ellie@example.com',
      password: 'new-password-here',
      password_confirmation: 'new-password-here',
    })
    expect(sentUrl(2)).toBe('http://api.test/email/verification-notification')
    expect(sent).toBe(true)
    expect(alreadyVerified).toBe(false)
  })

  it('checkUsername lowercases before sending', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(200, { available: true, reason: null }))

    const result = await auth.checkUsername('ElliE')

    expect(sentUrl(0)).toBe('http://api.test/api/usernames/ellie')
    expect(result).toEqual({ available: true, reason: null })
  })
})
