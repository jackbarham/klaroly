import { describe, expect, it } from 'vitest'
import { defineComponent, h } from 'vue'
import ListRow from '@/components/ui/ListRow.vue'
import { mountWithCleanup } from '@/lib/testMount'

// The rule this covers: a row's hover recolours the divider it already has. It
// does not fill the row and it does not add a second line, because a two pixel
// accent line above a one pixel grey one reads as three pixels of rule.

const Host = defineComponent({
  props: {
    to: { type: String, default: undefined },
  },
  setup(props) {
    return () => h('ul', [
      h(ListRow, { to: props.to }, {
        title: () => 'Hannah Whitfield',
        supporting: () => '14 Jun, 6:30am',
        trailing: () => 'Confirmed',
      }),
    ])
  },
})

function row(host: HTMLElement): HTMLElement {
  const found = host.querySelector<HTMLElement>('li')

  if (found === null) {
    throw new Error('The test expected to find a row')
  }

  return found
}

const mount = mountWithCleanup()

describe('a list row', () => {
  it('recolours its divider on hover rather than filling itself', async () => {
    const mounted = await mount(Host, '/')

    const classes = row(mounted.host).className

    expect(classes).toContain('border-b')
    expect(classes).toContain('border-border')
    expect(classes).toContain('hover:border-accent')
    expect(classes.split(' ').filter((name) => name.startsWith('hover:bg-'))).toEqual([])
  })

  it('carries the top hairline on the first row, so a plain list needs no wrapper', async () => {
    const mounted = await mount(Host, '/')

    expect(row(mounted.host).className).toContain('first:border-t')
  })

  it('shows what it was given', async () => {
    const mounted = await mount(Host, '/')

    expect(mounted.host.textContent).toContain('Hannah Whitfield')
    expect(mounted.host.textContent).toContain('14 Jun, 6:30am')
    expect(mounted.host.textContent).toContain('Confirmed')
  })

  it('is one link across its whole width when it goes somewhere, and takes the ring', async () => {
    const mounted = await mount(Host, '/', { to: '/bookings' })

    const link = row(mounted.host).querySelector('a')

    expect(link).not.toBeNull()
    expect(link?.getAttribute('href')).toBe('/bookings')
    expect(link?.className).toContain('focus-visible:focus-ring')
  })

  it('is not a link when it goes nowhere', async () => {
    const mounted = await mount(Host, '/')

    expect(row(mounted.host).querySelector('a')).toBeNull()
  })
})
