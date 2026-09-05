import { describe, expect, it } from 'vitest'
import RadioCard from '@/components/form/RadioCard.vue'
import { mountWithCleanup } from '@/lib/testMount'

// A radio card is a real radio underneath, and that is the thing worth
// testing: it reports its value, it can be reached and moved between by the
// keyboard, and the edge that says it is selected is not the edge that says it
// is focused.

function input(host: HTMLElement): HTMLInputElement {
  const found = host.querySelector('input')

  if (found === null) {
    throw new Error('The test expected to find a radio')
  }

  return found
}

const mount = mountWithCleanup()

describe('a radio card', () => {
  it('is a real radio in a named group', async () => {
    const mounted = await mount(RadioCard, '/', {
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

    const mounted = await mount(RadioCard, '/', {
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
    const mounted = await mount(RadioCard, '/', {
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
    const mounted = await mount(RadioCard, '/', {
      name: 'travel',
      value: 'included',
      title: 'Included',
      description: 'No separate charge for travel.',
      modelValue: 'included',
    })

    const box = mounted.host.querySelector('label > span')

    expect(box?.className).toContain('border-accent')
    expect(box?.className).toContain('ring-1')
    expect(box?.className).toContain('peer-focus-visible:focus-ring')
    expect(mounted.host.textContent).toContain('No separate charge for travel.')
  })

  it('carries the resting edge when it is not selected', async () => {
    const mounted = await mount(RadioCard, '/', {
      name: 'travel',
      value: 'included',
      title: 'Included',
      modelValue: 'per_mile',
    })

    const box = mounted.host.querySelector('label > span')
    const classes = box?.className.split(' ') ?? []

    expect(classes).toContain('border-border-strong')
    expect(classes).not.toContain('border-accent')
  })

  // The hover edge and the selected edge are both the accent, so the test
  // that they cannot be confused has to look at whole class names: the
  // selected card's border-accent is a substring of the hover class.
  it('offers the soft accent edge on hover only while it is not selected', async () => {
    const unselected = await mount(RadioCard, '/', {
      name: 'travel',
      value: 'included',
      title: 'Included',
      modelValue: 'per_mile',
    })

    expect(unselected.host.querySelector('label > span')?.className)
      .toContain('peer-enabled:hover:border-border-accent-soft')

    const selected = await mount(RadioCard, '/', {
      name: 'travel',
      value: 'included',
      title: 'Included',
      modelValue: 'included',
    })

    expect(selected.host.querySelector('label > span')?.className)
      .not.toContain('border-border-accent-soft')
  })
})
