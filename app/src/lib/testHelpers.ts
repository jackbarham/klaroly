import { afterEach, beforeEach, vi, type Mock } from 'vitest'
import { nextTick } from 'vue'

// The handful of things every test reaches for, written once. Imported by
// test files only.

// A fetch response with a JSON body, or an empty one for a status such as 204.
export function jsonResponse(status: number, body: unknown = null): Response {
  return new Response(body === null ? '' : JSON.stringify(body), { status })
}

// The one element matching the selector, or a failure that names it.
export function element<T extends HTMLElement = HTMLElement>(host: HTMLElement, selector: string): T {
  const found = host.querySelector<T>(selector)

  if (found === null) {
    throw new Error(`The test expected to find ${selector}`)
  }

  return found
}

// Typing, as far as v-model is concerned.
export function typeInto(input: HTMLInputElement, value: string): void {
  input.value = value
  input.dispatchEvent(new Event('input', { bubbles: true }))
}

export function submitForm(host: HTMLElement): void {
  element(host, 'form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))
}

// Lets every pending promise settle and Vue re-render afterwards.
export async function settle(): Promise<void> {
  await new Promise((resolve) => setTimeout(resolve, 0))
  await nextTick()
}

// A fixed today for the tests that count days or group by date, so nothing in
// them depends on the day they are run: Sunday 6 September 2026.
export const sampleToday = new Date(2026, 8, 6)

// The fetch mock a test that makes requests installs, with its reset before
// each test and its removal after. Called once at the top of a test file, the
// way mountWithCleanup is, and what it returns answers the requests. The one
// file that does not use it is router/index.test.ts, whose call count is meant
// to carry across its tests.
export function stubFetch(): Mock<typeof fetch> {
  const fetchMock = vi.fn<typeof fetch>()

  beforeEach(() => {
    fetchMock.mockReset()
    vi.stubGlobal('fetch', fetchMock)
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  return fetchMock
}
