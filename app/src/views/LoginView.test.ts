import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import LoginView from '@/views/LoginView.vue'
import { element, jsonResponse, settle, submitForm, typeInto } from '@/lib/testHelpers'
import { mountWithCleanup, type Mounted } from '@/lib/testMount'

// The sign-in screen, driven the way a person drives it: type, submit, and
// see what the screen does with what the API said. What is tested here is
// what would break quietly if a field were rewired wrongly. A message that
// lands on no field, or focus that stays where it was, looks like nothing
// happening at all.

const fetchMock = vi.fn<typeof fetch>()

const mount = mountWithCleanup()

beforeEach(() => {
  fetchMock.mockReset()
  vi.stubGlobal('fetch', fetchMock)
  // With the cookie already there the wrapper does not fetch a fresh one, so
  // the calls counted below are the ones this screen actually made.
  document.cookie = 'XSRF-TOKEN=token'
})

afterEach(() => {
  vi.unstubAllGlobals()
})

async function signInWith(credentials: { email: string, password: string }): Promise<Mounted> {
  const result = await mount(LoginView, '/login')

  typeInto(element<HTMLInputElement>(result.host, 'input[type="email"]'), credentials.email)
  typeInto(element<HTMLInputElement>(result.host, 'input[type="password"]'), credentials.password)
  submitForm(result.host)

  return result
}

describe('the sign-in screen', () => {
  it('puts a rejected credential on the email field and moves focus there', async () => {
    fetchMock.mockResolvedValue(jsonResponse(422, {
      message: 'These credentials do not match our records.',
      errors: { email: ['These credentials do not match our records.'] },
    }))

    const mounted = await signInWith({ email: 'ellie@example.com', password: 'wrong-password' })
    await settle()

    const emailInput = element<HTMLInputElement>(mounted.host, 'input[type="email"]')

    expect(emailInput.getAttribute('aria-invalid')).toBe('true')

    // The message is where the field says it is, rather than merely
    // somewhere on the page.
    const describedBy = emailInput.getAttribute('aria-describedby') ?? ''
    const message = element(mounted.host, `#${describedBy}`)

    expect(message.textContent?.trim()).toBe('These credentials do not match our records.')

    // focusFirstInvalid found the input, not the wrapper around it.
    expect(document.activeElement).toBe(emailInput)

    // The password is cleared, so the next attempt starts from an empty box.
    expect(element<HTMLInputElement>(mounted.host, 'input[type="password"]').value).toBe('')
  })

  it('marks nothing invalid when the failure belongs to no field, and says so once', async () => {
    fetchMock.mockResolvedValue(jsonResponse(429, { message: 'Too many requests.' }))

    const mounted = await signInWith({ email: 'ellie@example.com', password: 'correct-horse-battery' })
    await settle()

    expect(mounted.host.querySelectorAll('[aria-invalid="true"]')).toHaveLength(0)

    const alert = element(mounted.host, '[role="alert"]')

    expect(alert.textContent?.trim()).toBe('Too many attempts. Wait a minute and try again.')
  })

  it('disables the button and sends nothing more while the request is in flight', async () => {
    // The request is left hanging so that the screen can be looked at while
    // it is still in flight. Holding the resolver on an object rather than in
    // a variable keeps its type a function that this test can call later.
    const request: { answer?: (response: Response) => void } = {}

    fetchMock.mockImplementation(() => new Promise<Response>((resolve) => {
      request.answer = resolve
    }))

    const mounted = await signInWith({ email: 'ellie@example.com', password: 'correct-horse-battery' })
    await nextTick()

    const button = element<HTMLButtonElement>(mounted.host, 'button[type="submit"]')

    expect(button.disabled).toBe(true)
    expect(button.getAttribute('aria-busy')).toBe('true')

    // A second submit, of the kind an impatient double click produces.
    submitForm(mounted.host)
    await nextTick()

    expect(fetchMock).toHaveBeenCalledTimes(1)

    request.answer?.(jsonResponse(422, { message: 'No.', errors: { email: ['No.'] } }))
    await settle()

    expect(button.disabled).toBe(false)
    expect(button.getAttribute('aria-busy')).toBeNull()
  })
})
