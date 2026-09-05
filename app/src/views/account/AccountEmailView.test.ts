import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import { sampleMe } from '@/lib/auth.sample'
import { element, jsonResponse, settle } from '@/lib/testHelpers'
import { mountWithCleanup, type Mounted } from '@/lib/testMount'
import { useAuthStore } from '@/stores/auth'
import AccountEmailView from '@/views/account/AccountEmailView.vue'
import type { Me } from '@/types/auth'

// The toggle saves the moment it is thrown, so the two things worth holding
// are that it really sends and that a failure puts it back. A switch left
// showing a state the API does not hold is worse than no switch at all.

const fetchMock = vi.fn<typeof fetch>()

const mount = mountWithCleanup()

beforeEach(() => {
  fetchMock.mockReset()
  vi.stubGlobal('fetch', fetchMock)
  document.cookie = 'XSRF-TOKEN=token'
})

afterEach(() => {
  vi.unstubAllGlobals()
})

function me(overrides: { consentedAt?: string | null, verifiedAt?: string | null } = {}): Me {
  const value = structuredClone(sampleMe) as Me

  value.user.marketing_consent_at = overrides.consentedAt ?? null
  value.user.email_verified_at = overrides.verifiedAt ?? null

  return value
}

async function open(initial: Me = me()): Promise<Mounted> {
  const mounted = await mount(AccountEmailView, '/account/email')

  useAuthStore().me = initial
  await nextTick()

  return mounted
}

function toggle(host: HTMLElement): HTMLButtonElement {
  return element<HTMLButtonElement>(host, 'button[role="switch"]')
}

describe('the email and marketing screen', () => {
  it('shows the address and that it is not verified', async () => {
    const { host } = await open()

    expect(host.textContent).toContain('ellie@example.com')
    expect(host.textContent).toContain('Not verified')
  })

  it('offers the resend while the address is unverified', async () => {
    const { host } = await open()

    expect(host.textContent).toContain('Resend the email')
  })

  it('offers no resend once the address is verified', async () => {
    const { host } = await open(me({ verifiedAt: '2026-09-01T09:00:00Z' }))

    expect(host.textContent).toContain('Verified')

    // Paired with the test above on purpose: an absence asserted on its own
    // passes just as happily when the wording has changed underneath it.
    expect(host.textContent).not.toContain('Resend the email')
  })

  it('sends the consent as soon as the switch is thrown, and reflects the answer', async () => {
    fetchMock.mockResolvedValue(jsonResponse(200, {
      data: { ...me(), user: { ...me().user, marketing_consent_at: '2026-09-05T09:00:00Z' } },
    }))

    const { host } = await open()

    expect(toggle(host).getAttribute('aria-checked')).toBe('false')

    toggle(host).click()
    await settle()

    const call = fetchMock.mock.calls[0]

    expect(String(call[0])).toContain('/api/user/marketing-consent')
    expect(call[1]?.method).toBe('PUT')
    expect(JSON.parse(String(call[1]?.body))).toEqual({ consented: true })
    expect(toggle(host).getAttribute('aria-checked')).toBe('true')
  })

  it('turns the consent off again from a state that had it on', async () => {
    fetchMock.mockResolvedValue(jsonResponse(200, { data: me() }))

    const { host } = await open(me({ consentedAt: '2026-09-01T09:00:00Z' }))

    expect(toggle(host).getAttribute('aria-checked')).toBe('true')

    toggle(host).click()
    await settle()

    expect(JSON.parse(String(fetchMock.mock.calls[0][1]?.body))).toEqual({ consented: false })
    expect(toggle(host).getAttribute('aria-checked')).toBe('false')
  })

  it('puts the switch back when the request fails, and says so', async () => {
    fetchMock.mockResolvedValue(jsonResponse(500, { message: 'No.' }))

    const { host } = await open()

    toggle(host).click()
    await settle()

    expect(toggle(host).getAttribute('aria-checked')).toBe('false')
    expect(element(host, '[role="alert"]').textContent?.trim()).toBe('That could not be saved. Try again.')
  })
})
