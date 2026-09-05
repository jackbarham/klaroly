import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import RegisterView from '@/views/RegisterView.vue'
import { element, jsonResponse, submitForm, typeInto } from '@/lib/testHelpers'
import { mountWithCleanup, type Mounted } from '@/lib/testMount'

// The register screen. Two things here are easy to break without anything
// looking broken: the live username check, whose answer is a tick or a cross
// that a screen reader cannot see, and which field a validation message
// lands on when the form comes back with one.

const fetchMock = vi.fn<typeof fetch>()

const mount = mountWithCleanup()

beforeEach(() => {
  fetchMock.mockReset()
  vi.stubGlobal('fetch', fetchMock)
  vi.useFakeTimers()
  document.cookie = 'XSRF-TOKEN=token'
})

afterEach(() => {
  vi.useRealTimers()
  vi.unstubAllGlobals()
})

// The fields in the order they are on the screen, so that a test can name
// one without counting inputs.
function field(host: HTMLElement, name: 'business_name' | 'name' | 'email' | 'username' | 'password'): HTMLInputElement {
  const selectors = {
    business_name: 'input[autocomplete="organization"]',
    name: 'input[autocomplete="name"]',
    email: 'input[autocomplete="email"]',
    username: 'input[autocomplete="username"]',
    password: 'input[autocomplete="new-password"]',
  }

  return element<HTMLInputElement>(host, selectors[name])
}

// Lets every pending promise settle, and the debounced username check run,
// and Vue re-render afterwards. The shared settle() cannot be used here
// because this file runs on fake timers.
async function settle(): Promise<void> {
  await vi.runAllTimersAsync()
  await nextTick()
}

async function typeUsername(username: string): Promise<Mounted> {
  const result = await mount(RegisterView, '/register')

  typeInto(field(result.host, 'username'), username)
  await settle()

  return result
}

describe('the register screen', () => {
  it('says in words that a username is taken, as well as showing a cross', async () => {
    fetchMock.mockResolvedValue(jsonResponse(200, { available: false, reason: 'taken' }))

    const mounted = await typeUsername('elliemarsh')

    expect(String(fetchMock.mock.calls[0][0])).toBe('http://api.test/api/usernames/elliemarsh')

    // The mark is a shape and is hidden, so the announcement is the whole of
    // what a screen reader gets.
    const announcement = element(mounted.host, '[role="status"]')

    expect(announcement.textContent?.trim()).toBe('This username is already taken.')
    expect(announcement.classList.contains('sr-only')).toBe(true)
    expect(mounted.host.querySelector('svg polyline')).toBeNull()
  })

  it('says in words that a username is available, as well as showing a tick', async () => {
    fetchMock.mockResolvedValue(jsonResponse(200, { available: true, reason: null }))

    const mounted = await typeUsername('elliemarsh')

    expect(element(mounted.host, '[role="status"]').textContent?.trim()).toBe('This username is available.')
    expect(mounted.host.querySelector('svg polyline')).not.toBeNull()
  })

  it('checks nothing until there is enough of a username to check', async () => {
    const mounted = await typeUsername('el')

    expect(fetchMock).not.toHaveBeenCalled()
    expect(mounted.host.querySelector('[role="status"]')).toBeNull()
  })

  it('puts a rejected field on that field and moves focus there, not to the first one', async () => {
    fetchMock.mockResolvedValue(jsonResponse(422, {
      message: 'The email has already been taken.',
      errors: { email: ['The email has already been taken.'] },
    }))

    const mounted = await mount(RegisterView, '/register')

    typeInto(field(mounted.host, 'business_name'), 'Ellie Marsh Makeup')
    typeInto(field(mounted.host, 'name'), 'Ellie Marsh')
    typeInto(field(mounted.host, 'email'), 'ellie@example.com')
    typeInto(field(mounted.host, 'password'), 'correct-horse-battery')

    submitForm(mounted.host)
    await settle()

    const emailInput = field(mounted.host, 'email')

    expect(emailInput.getAttribute('aria-invalid')).toBe('true')
    expect(field(mounted.host, 'business_name').getAttribute('aria-invalid')).toBeNull()
    expect(document.activeElement).toBe(emailInput)

    const describedBy = emailInput.getAttribute('aria-describedby') ?? ''

    expect(element(mounted.host, `#${describedBy}`).textContent?.trim()).toBe('The email has already been taken.')
  })
})
