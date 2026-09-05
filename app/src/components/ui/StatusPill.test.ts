import { afterEach, describe, expect, it } from 'vitest'
import StatusPill from '@/components/ui/StatusPill.vue'
import { mount, unmount, type Mounted } from '@/lib/testMount'

// A pill says what state something is in, and it says it with a pair of
// tokens: the subtle fill and the text colour of the same family. The pair is
// what survives the theme flip, so the pair is what is tested.

let mounted: Mounted | null = null

function pill(host: HTMLElement): HTMLElement {
  const found = host.querySelector<HTMLElement>('span')

  if (found === null) {
    throw new Error('The test expected to find a pill')
  }

  return found
}

afterEach(() => {
  if (mounted) {
    unmount(mounted)
    mounted = null
  }
})

describe('a status pill', () => {
  it('is neutral when it is not told otherwise', async () => {
    mounted = await mount(StatusPill, '/')

    expect(pill(mounted.host).className).toContain('bg-surface-sunken')
    expect(pill(mounted.host).className).toContain('text-text-muted')
  })

  it('pairs the subtle fill with the text colour of the same family', async () => {
    for (const tone of ['success', 'warning', 'danger', 'info'] as const) {
      mounted = await mount(StatusPill, '/', { tone })

      expect(pill(mounted.host).className).toContain(`bg-${tone}-subtle`)
      expect(pill(mounted.host).className).toContain(`text-${tone}-text`)

      unmount(mounted)
      mounted = null
    }
  })

  it('never carries a colour of its own', async () => {
    mounted = await mount(StatusPill, '/', { tone: 'success' })

    const classes = pill(mounted.host).className.split(' ')

    expect(classes.filter((name) => name.includes('#'))).toEqual([])
  })
})
