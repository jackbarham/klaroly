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
