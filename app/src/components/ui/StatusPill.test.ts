import { describe, expect, it } from 'vitest'
import StatusPill from '@/components/ui/StatusPill.vue'
import { element } from '@/lib/testHelpers'
import { mount, mountWithCleanup, unmount } from '@/lib/testMount'

// A pill says what state something is in, and it says it with a pair of
// tokens: the subtle fill and the text colour of the same family. The pair is
// what survives the theme flip, so the pair is what is tested.

const mountOnce = mountWithCleanup()

describe('a status pill', () => {
  it('is neutral when it is not told otherwise', async () => {
    const mounted = await mountOnce(StatusPill, '/')

    expect(element(mounted.host, 'span').className).toContain('bg-surface-sunken')
    expect(element(mounted.host, 'span').className).toContain('text-text-muted')
  })

  it('pairs the subtle fill with the text colour of the same family', async () => {
    for (const tone of ['success', 'warning', 'danger', 'info'] as const) {
      const mounted = await mount(StatusPill, '/', { tone })

      expect(element(mounted.host, 'span').className).toContain(`bg-${tone}-subtle`)
      expect(element(mounted.host, 'span').className).toContain(`text-${tone}-text`)

      unmount(mounted)
    }
  })

  it('never carries a colour of its own', async () => {
    const mounted = await mountOnce(StatusPill, '/', { tone: 'success' })

    const classes = element(mounted.host, 'span').className.split(' ')

    expect(classes.filter((name) => name.includes('#'))).toEqual([])
  })
})
