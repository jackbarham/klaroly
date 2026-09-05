import { afterEach, describe, expect, it } from 'vitest'
import AppButton from '@/components/ui/AppButton.vue'
import { mount, unmount, type Mounted } from '@/lib/testMount'

// The pending state is what stops a form being submitted twice, so it is
// worth a test of its own: every screen that submits something relies on it.
//
// It is also the state that used to be indistinguishable from disabled, so
// what a person can see of it is tested as well as what a screen reader is
// told: pending draws a spinner and is not dimmed, disabled is dimmed and
// draws nothing.

let mounted: Mounted | null = null

function button(host: HTMLElement): HTMLButtonElement {
  const found = host.querySelector('button')

  if (found === null) {
    throw new Error('The test expected to find a button')
  }

  return found
}

// The spinner is a bordered element rather than an icon, so it is found by
// the class that turns it.
function spinner(host: HTMLElement): HTMLElement | null {
  return host.querySelector<HTMLElement>('.animate-spin')
}

// What the button says, which is the first thing inside it.
function label(host: HTMLElement): HTMLElement {
  const found = button(host).querySelector<HTMLElement>('span')

  if (found === null) {
    throw new Error('The test expected the button to have a label')
  }

  return found
}

function isDimmed(host: HTMLElement): boolean {
  return button(host).className.includes('opacity-50')
}

afterEach(() => {
  if (mounted) {
    unmount(mounted)
    mounted = null
  }
})

describe('a button', () => {
  it('is enabled and not busy by default', async () => {
    mounted = await mount(AppButton, '/')

    expect(button(mounted.host).disabled).toBe(false)
    expect(button(mounted.host).getAttribute('aria-busy')).toBeNull()
  })

  it('is disabled and busy while its request is in flight', async () => {
    mounted = await mount(AppButton, '/', { pending: true })

    expect(button(mounted.host).disabled).toBe(true)
    expect(button(mounted.host).getAttribute('aria-busy')).toBe('true')
  })

  it('shows a spinner and is not dimmed while its request is in flight', async () => {
    mounted = await mount(AppButton, '/', { pending: true })

    expect(spinner(mounted.host)).not.toBeNull()
    expect(isDimmed(mounted.host)).toBe(false)
  })

  it('is disabled without being busy when it is simply unavailable', async () => {
    mounted = await mount(AppButton, '/', { disabled: true })

    expect(button(mounted.host).disabled).toBe(true)
    expect(button(mounted.host).getAttribute('aria-busy')).toBeNull()
  })

  it('is dimmed and shows no spinner when it is simply unavailable', async () => {
    mounted = await mount(AppButton, '/', { disabled: true })

    expect(isDimmed(mounted.host)).toBe(true)
    expect(spinner(mounted.host)).toBeNull()
  })

  // The label and any icon stay exactly where they are and turn invisible,
  // with the spinner laid over the top, so the button does not change width
  // halfway through a submit.
  it('hides what it says behind the spinner rather than replacing it', async () => {
    mounted = await mount(AppButton, '/', { icon: 'plus', pending: true })

    expect(spinner(mounted.host)).not.toBeNull()
    expect(mounted.host.querySelectorAll('svg')).toHaveLength(1)
    expect(label(mounted.host).className).toContain('opacity-0')
  })

  it('shows what it says when it is not busy', async () => {
    mounted = await mount(AppButton, '/', { icon: 'plus' })

    expect(label(mounted.host).className).not.toContain('opacity-0')
  })
})
