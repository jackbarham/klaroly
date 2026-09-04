import { afterEach, describe, expect, it } from 'vitest'
import AppButton from '@/components/ui/AppButton.vue'
import { mount, unmount, type Mounted } from '@/lib/testMount'

// The pending state is what stops a form being submitted twice, so it is
// worth a test of its own: every screen that submits something relies on it.

let mounted: Mounted | null = null

function button(host: HTMLElement): HTMLButtonElement {
  const found = host.querySelector('button')

  if (found === null) {
    throw new Error('The test expected to find a button')
  }

  return found
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

  it('is disabled without being busy when it is simply unavailable', async () => {
    mounted = await mount(AppButton, '/', { disabled: true })

    expect(button(mounted.host).disabled).toBe(true)
    expect(button(mounted.host).getAttribute('aria-busy')).toBeNull()
  })
})
