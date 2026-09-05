import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import RegisterView from '@/views/RegisterView.vue'
import { element, jsonResponse, submitForm, typeInto } from '@/lib/testHelpers'
import { mountWithCleanup, type Mounted } from '@/lib/testMount'

// The register screen. Three things here are easy to break without anything
// looking broken: the two steps, which are one form and one route so the
// fields of the step that is not showing are not in the page at all; the live
// username check, whose answer is a tick or a cross that a screen reader
// cannot see; and which field a validation message lands on when the form
// comes back with one, which now decides which step is shown as well.

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

// The fields by name, so that a test can name one without counting inputs.
// Only the fields of the step on screen are there to be found.
const selectors = {
  business_name: 'input[autocomplete="organization"]',
  name: 'input[autocomplete="name"]',
  email: 'input[autocomplete="email"]',
  username: 'input[autocomplete="username"]',
  password: 'input[autocomplete="new-password"]',
}

type FieldName = keyof typeof selectors

function field(host: HTMLElement, name: FieldName): HTMLInputElement {
  return element<HTMLInputElement>(host, selectors[name])
}

function showing(host: HTMLElement, name: FieldName): boolean {
  return host.querySelector(selectors[name]) !== null
}

// Lets every pending promise settle, and the debounced username check run,
// and Vue re-render afterwards. The shared settle() cannot be used here
// because this file runs on fake timers.
async function settle(): Promise<void> {
  await vi.runAllTimersAsync()
  await nextTick()
}

// The first step filled in and passed, which is where every test of the
// second step starts.
async function reachDetails(email = 'ellie@example.com'): Promise<Mounted> {
  const mounted = await mount(RegisterView, '/register')

  typeInto(field(mounted.host, 'email'), email)
  typeInto(field(mounted.host, 'password'), 'correct-horse-battery')

  submitForm(mounted.host)
  await settle()

  return mounted
}

async function typeUsername(username: string): Promise<Mounted> {
  const mounted = await reachDetails()

  typeInto(field(mounted.host, 'username'), username)
  await settle()

  return mounted
}

describe('the register screen', () => {
  it('asks for the sign-in details first and nothing else', async () => {
    const mounted = await mount(RegisterView, '/register')

    expect(showing(mounted.host, 'email')).toBe(true)
    expect(showing(mounted.host, 'password')).toBe(true)
    expect(showing(mounted.host, 'business_name')).toBe(false)
    expect(showing(mounted.host, 'username')).toBe(false)
  })

  it('moves to the second step without sending anything', async () => {
    const mounted = await reachDetails()

    expect(fetchMock).not.toHaveBeenCalled()
    expect(showing(mounted.host, 'business_name')).toBe(true)
    expect(showing(mounted.host, 'name')).toBe(true)
    expect(showing(mounted.host, 'username')).toBe(true)
    expect(showing(mounted.host, 'email')).toBe(false)
  })

  it('goes back to the first step with what was typed still there', async () => {
    const mounted = await reachDetails()

    typeInto(field(mounted.host, 'business_name'), 'Ellie Marsh Makeup')

    element<HTMLButtonElement>(mounted.host, 'button[type="button"]').click()
    await settle()

    expect(field(mounted.host, 'email').value).toBe('ellie@example.com')

    // And forward again: the second step kept its own answers too.
    submitForm(mounted.host)
    await settle()

    expect(field(mounted.host, 'business_name').value).toBe('Ellie Marsh Makeup')
  })

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
    const mounted = await reachDetails()

    typeInto(field(mounted.host, 'business_name'), 'Ellie Marsh Makeup')
    typeInto(field(mounted.host, 'name'), 'Ellie Marsh')

    fetchMock.mockResolvedValue(jsonResponse(422, {
      message: 'The username has already been taken.',
      errors: { username: ['The username has already been taken.'] },
    }))

    submitForm(mounted.host)
    await settle()

    const usernameInput = field(mounted.host, 'username')

    expect(usernameInput.getAttribute('aria-invalid')).toBe('true')
    expect(field(mounted.host, 'business_name').getAttribute('aria-invalid')).toBeNull()
    expect(document.activeElement).toBe(usernameInput)

    // The username field has a hint as well, and FormField puts the error
    // last, so the message is the last thing the field points at.
    const describedBy = (usernameInput.getAttribute('aria-describedby') ?? '').split(' ')

    expect(element(mounted.host, `#${describedBy.at(-1)}`).textContent?.trim()).toBe('The username has already been taken.')
  })

  it('goes back to the first step when the API turns down the email address', async () => {
    const mounted = await reachDetails('taken@example.com')

    typeInto(field(mounted.host, 'business_name'), 'Ellie Marsh Makeup')

    fetchMock.mockResolvedValue(jsonResponse(422, {
      message: 'The email has already been taken.',
      errors: { email: ['The email has already been taken.'] },
    }))

    submitForm(mounted.host)
    await settle()

    // The message belongs to a field on the step that was not showing, so
    // the form goes back to it rather than saying nothing.
    const emailInput = field(mounted.host, 'email')

    expect(emailInput.getAttribute('aria-invalid')).toBe('true')
    expect(document.activeElement).toBe(emailInput)

    const describedBy = emailInput.getAttribute('aria-describedby') ?? ''

    expect(element(mounted.host, `#${describedBy}`).textContent?.trim()).toBe('The email has already been taken.')
  })
})
