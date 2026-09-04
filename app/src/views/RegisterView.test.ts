import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import RegisterView from '@/views/RegisterView.vue'
import { mount, unmount, type Mounted } from '@/lib/testMount'

// The register screen. Two things here are easy to break without anything
// looking broken: the live username check, whose answer is a tick or a cross
// that a screen reader cannot see, and which field a validation message
// lands on when the form comes back with one.

function jsonResponse(status: number, body: unknown = null): Response {
  return new Response(body === null ? '' : JSON.stringify(body), { status })
}

const fetchMock = vi.fn<typeof fetch>()

let mounted: Mounted | null = null

beforeEach(() => {
  fetchMock.mockReset()
  vi.stubGlobal('fetch', fetchMock)
  vi.useFakeTimers()
  document.cookie = 'XSRF-TOKEN=token'
})

afterEach(() => {
  if (mounted) {
    unmount(mounted)
    mounted = null
  }

  vi.useRealTimers()
  vi.unstubAllGlobals()
})

function element<T extends HTMLElement>(host: HTMLElement, selector: string): T {
  const found = host.querySelector<T>(selector)

  if (found === null) {
    throw new Error(`The test expected to find ${selector}`)
  }

  return found
}

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

function type(input: HTMLInputElement, value: string): void {
  input.value = value
  input.dispatchEvent(new Event('input', { bubbles: true }))
}

// Lets every pending promise settle, and the debounced username check run,
// and Vue re-render afterwards.
async function settle(): Promise<void> {
  await vi.runAllTimersAsync()
  await nextTick()
}

async function typeUsername(username: string): Promise<Mounted> {
  const result = await mount(RegisterView, '/register')

  type(field(result.host, 'username'), username)
  await settle()

  return result
}

describe('the register screen', () => {
  it('says in words that a username is taken, as well as showing a cross', async () => {
    fetchMock.mockResolvedValue(jsonResponse(200, { available: false, reason: 'taken' }))

    mounted = await typeUsername('elliemarsh')

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

    mounted = await typeUsername('elliemarsh')

    expect(element(mounted.host, '[role="status"]').textContent?.trim()).toBe('This username is available.')
    expect(mounted.host.querySelector('svg polyline')).not.toBeNull()
  })

  it('checks nothing until there is enough of a username to check', async () => {
    mounted = await typeUsername('el')

    expect(fetchMock).not.toHaveBeenCalled()
    expect(mounted.host.querySelector('[role="status"]')).toBeNull()
  })

  it('puts a rejected field on that field and moves focus there, not to the first one', async () => {
    fetchMock.mockResolvedValue(jsonResponse(422, {
      message: 'The email has already been taken.',
      errors: { email: ['The email has already been taken.'] },
    }))

    mounted = await mount(RegisterView, '/register')

    type(field(mounted.host, 'business_name'), 'Ellie Marsh Makeup')
    type(field(mounted.host, 'name'), 'Ellie Marsh')
    type(field(mounted.host, 'email'), 'ellie@example.com')
    type(field(mounted.host, 'password'), 'correct-horse-battery')

    element(mounted.host, 'form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))
    await settle()

    const emailInput = field(mounted.host, 'email')

    expect(emailInput.getAttribute('aria-invalid')).toBe('true')
    expect(field(mounted.host, 'business_name').getAttribute('aria-invalid')).toBeNull()
    expect(document.activeElement).toBe(emailInput)

    const describedBy = emailInput.getAttribute('aria-describedby') ?? ''

    expect(element(mounted.host, `#${describedBy}`).textContent?.trim()).toBe('The email has already been taken.')
  })
})
