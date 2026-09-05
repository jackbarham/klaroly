import { afterEach, describe, expect, it } from 'vitest'
import RadioCard from '@/components/form/RadioCard.vue'
import { mount, unmount, type Mounted } from '@/lib/testMount'

// A radio card is a real radio underneath, and that is the thing worth
// testing: it reports its value, it can be reached and moved between by the
// keyboard, and the edge that says it is selected is not the edge that says it
// is focused.

let mounted: Mounted | null = null

function input(host: HTMLElement): HTMLInputElement {
  const found = host.querySelector('input')

  if (found === null) {
    throw new Error('The test expected to find a radio')
  }

  return found
}

afterEach(() => {
  if (mounted) {
    unmount(mounted)
    mounted = null
  }
})

describe('a radio card', () => {
  it('is a real radio in a named group', async () => {
    mounted = await mount(RadioCard, '/', {
      name: 'travel',
      value: 'included',
      title: 'Included',
      modelValue: '',
    })

    expect(input(mounted.host).type).toBe('radio')
    expect(input(mounted.host).name).toBe('travel')
  })

  it('reports its value when it is chosen', async () => {
    let chosen = ''

    mounted = await mount(RadioCard, '/', {
      'name': 'travel',
      'value': 'per_mile',
      'title': 'A rate for every mile',
      'modelValue': '',
      'onUpdate:modelValue': (next: string) => {
        chosen = next
      },
    })

    input(mounted.host).click()

    expect(chosen).toBe('per_mile')
  })

  // Hidden from sight but not from the keyboard: sr-only leaves the radio
  // focusable and arrow-navigable, which display:none or hidden would not.
  it('stays reachable by the keyboard', async () => {
    mounted = await mount(RadioCard, '/', {
      name: 'travel',
      value: 'included',
      title: 'Included',
      modelValue: '',
    })

    const radio = input(mounted.host)

    expect(radio.className).toContain('sr-only')
    expect(radio.hidden).toBe(false)
    expect(radio.hasAttribute('disabled')).toBe(false)

    radio.focus()

    expect(document.activeElement).toBe(radio)
  })

  it('says it is selected with the edge, and takes focus on a ring outside it', async () => {
    mounted = await mount(RadioCard, '/', {
      name: 'travel',
      value: 'included',
      title: 'Included',
      description: 'No separate charge for travel.',
      modelValue: 'included',
    })

    const box = mounted.host.querySelector('label > span')

    expect(box?.className).toContain('border-accent')
    expect(box?.className).toContain('ring-1')
    expect(box?.className).toContain('peer-focus-visible:outline-border-focus')
    expect(mounted.host.textContent).toContain('No separate charge for travel.')
  })

  it('carries the resting edge when it is not selected', async () => {
    mounted = await mount(RadioCard, '/', {
      name: 'travel',
      value: 'included',
      title: 'Included',
      modelValue: 'per_mile',
    })

    const box = mounted.host.querySelector('label > span')

    expect(box?.className).toContain('border-border-strong')
    expect(box?.className).not.toContain('border-accent')
  })
})
