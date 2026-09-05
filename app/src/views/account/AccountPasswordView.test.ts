import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { element, jsonResponse, settle, submitForm, typeInto } from '@/lib/testHelpers'
import { mountWithCleanup, type Mounted } from '@/lib/testMount'
import AccountPasswordView from '@/views/account/AccountPasswordView.vue'

// The two things that would go quiet: a mismatch that spends a request to be
// told what the screen could already see, and a rejected current password
// landing anywhere other than the field it is about.

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

interface Screen extends Mounted {
  current: HTMLInputElement
  password: HTMLInputElement
  confirmation: HTMLInputElement
}

async function open(): Promise<Screen> {
  const mounted = await mount(AccountPasswordView, '/account/password')
  const inputs = [...mounted.host.querySelectorAll('input')]

  return {
    ...mounted,
    current: inputs[0] as HTMLInputElement,
    password: inputs[1] as HTMLInputElement,
    confirmation: inputs[2] as HTMLInputElement,
  }
}

describe('the password screen', () => {
  it('catches a mismatch before sending anything', async () => {
    const screen = await open()

    typeInto(screen.current, 'correct-horse-battery')
    typeInto(screen.password, 'a-brand-new-password')
    typeInto(screen.confirmation, 'a-different-password')
    submitForm(screen.host)
    await settle()

    expect(fetchMock).not.toHaveBeenCalled()
    expect(screen.confirmation.getAttribute('aria-invalid')).toBe('true')
    expect(document.activeElement).toBe(screen.confirmation)

    const describedBy = screen.confirmation.getAttribute('aria-describedby') ?? ''

    expect(element(screen.host, `#${describedBy}`).textContent?.trim())
      .toBe('This does not match the new password.')
  })

  it('puts a rejected current password on that field and moves focus there', async () => {
    fetchMock.mockResolvedValue(jsonResponse(422, {
      message: 'The password you entered does not match your current password.',
      errors: { current_password: ['The password you entered does not match your current password.'] },
    }))

    const screen = await open()

    typeInto(screen.current, 'not-the-password')
    typeInto(screen.password, 'a-brand-new-password')
    typeInto(screen.confirmation, 'a-brand-new-password')
    submitForm(screen.host)
    await settle()

    expect(screen.current.getAttribute('aria-invalid')).toBe('true')
    expect(document.activeElement).toBe(screen.current)
  })

  it('stays signed in and says what happened to the other devices', async () => {
    fetchMock.mockResolvedValue(jsonResponse(200, { message: 'Your password has been changed.' }))

    const screen = await open()

    typeInto(screen.current, 'correct-horse-battery')
    typeInto(screen.password, 'a-brand-new-password')
    typeInto(screen.confirmation, 'a-brand-new-password')
    submitForm(screen.host)
    await settle()

    expect(element(screen.host, '[role="status"]').textContent?.trim()).toBe(
      'Your password has been changed. You are still signed in on this device, and every other device has been signed out.',
    )

    // The fields are emptied, so a password is not left sitting in the DOM.
    expect(screen.current.value).toBe('')
    expect(screen.password.value).toBe('')
  })
})
