import { afterEach, describe, expect, it } from 'vitest'
import { defineComponent, h } from 'vue'
import SectionBand from '@/components/ui/SectionBand.vue'
import { mount, unmount, type Mounted } from '@/lib/testMount'

// A band that collapses is a button, so what is tested is that it behaves like
// one: it says whether it is expanded, and what it controls goes away.

const Host = defineComponent({
  props: {
    collapsible: { type: Boolean, default: false },
  },
  setup(props) {
    return () => h(SectionBand, { title: 'Pricing', collapsible: props.collapsible }, {
      default: () => h('p', 'What the section is about.'),
    })
  },
})

let mounted: Mounted | null = null

afterEach(() => {
  if (mounted) {
    unmount(mounted)
    mounted = null
  }
})

describe('a section band', () => {
  it('is a plain bar with its content showing when it does not collapse', async () => {
    mounted = await mount(Host, '/')

    expect(mounted.host.querySelector('button')).toBeNull()
    expect(mounted.host.textContent).toContain('What the section is about.')
  })

  it('collapses and says so', async () => {
    mounted = await mount(Host, '/', { collapsible: true })

    const button = mounted.host.querySelector('button')

    if (button === null) {
      throw new Error('The test expected a collapsible band to be a button')
    }

    expect(button.getAttribute('aria-expanded')).toBe('true')
    expect(mounted.host.textContent).toContain('What the section is about.')

    button.click()
    await new Promise((resolve) => setTimeout(resolve, 0))

    expect(button.getAttribute('aria-expanded')).toBe('false')
    expect(mounted.host.textContent).not.toContain('What the section is about.')
  })

  it('names what the button controls', async () => {
    mounted = await mount(Host, '/', { collapsible: true })

    const button = mounted.host.querySelector('button')
    const controls = button?.getAttribute('aria-controls') ?? ''

    expect(controls).not.toBe('')
    expect(mounted.host.querySelector(`#${controls}`)).not.toBeNull()
  })
})
