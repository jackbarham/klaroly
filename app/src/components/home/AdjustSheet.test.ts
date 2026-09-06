import { defineComponent, h, ref } from 'vue'
import { describe, expect, it } from 'vitest'
import AdjustSheet from '@/components/home/AdjustSheet.vue'
import { settle } from '@/lib/testHelpers'
import { mountWithCleanup } from '@/lib/testMount'
import type { BlockKey } from '@/lib/homeView'

// Adjust: the block order and the preview count, and nothing else.
//
// The sheet teleports to the body, so these read document.body rather than the
// mount host.

const mount = mountWithCleanup()

function handles(): HTMLElement[] {
  return [...document.body.querySelectorAll<HTMLElement>('[data-handle]')]
}

function order(): string[] {
  return [...document.body.querySelectorAll<HTMLElement>('[data-block]')]
    .map((found) => found.dataset.block ?? '')
}

describe('the order', () => {
  it('lists the three blocks in the order it was given', async () => {
    await mount(AdjustSheet, '/', {
      open: true,
      order: ['next', 'attention', 'money'],
      previewCount: 4,
    })

    expect(order()).toEqual(['next', 'attention', 'money'])
  })

  /**
   * **The keyboard half is not optional: a reorder that only works by dragging
   * is a reorder half the people cannot do.**
   *
   * And focus goes back to the handle that MOVED rather than the one now in
   * that position, so holding an arrow key walks one block down the list rather
   * than swapping two back and forth.
   */
  it('moves a block with the arrow keys and leaves focus on the handle that moved', async () => {
    // A stateful parent, because the sheet is controlled: without one the array
    // never changes, the DOM never reorders, and a focus assertion would pass
    // against a component that did nothing at all.
    const Host = defineComponent({
      setup() {
        const order = ref<BlockKey[]>(['next', 'attention', 'money'])

        return () => h(AdjustSheet, {
          'open': true,
          'order': order.value,
          'previewCount': 4 as const,
          'onOrder': (next: BlockKey[]) => {
            order.value = next
          },
          'onUpdate:open': () => {},
        })
      },
    })

    await mount(Host, '/')

    const money = handles().find((handle) => handle.dataset.handle === 'money')

    money?.focus()
    money?.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowUp', bubbles: true }))

    await settle()

    expect(order()).toEqual(['next', 'money', 'attention'])

    // **Focus is on the handle that moved**, not on whatever is now in that
    // position, so holding the key walks one block down the list rather than
    // swapping two back and forth. The node was moved in the DOM, and a browser
    // does not reliably keep focus through that, so it is given back by hand.
    expect((document.activeElement as HTMLElement)?.dataset.handle).toBe('money')

    // And again, to prove it is the same block moving rather than two swapping.
    const again = handles().find((handle) => handle.dataset.handle === 'money')

    again?.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowUp', bubbles: true }))

    await settle()

    expect(order()).toEqual(['money', 'next', 'attention'])
  })

  it('does not move the first block up or the last one down', async () => {
    const moved: BlockKey[][] = []

    await mount(AdjustSheet, '/', {
      'open': true,
      'order': ['next', 'attention', 'money'],
      'previewCount': 4,
      'onOrder': (next: BlockKey[]) => moved.push(next),
    })

    const first = handles().find((handle) => handle.dataset.handle === 'next')

    first?.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowUp', bubbles: true }))

    await settle()

    expect(moved).toEqual([])
  })
})

describe('the preview count', () => {
  it('offers 3, 4, 6 and All, and marks the current one', async () => {
    await mount(AdjustSheet, '/', {
      open: true,
      order: ['next', 'attention', 'money'],
      previewCount: 6,
    })

    const buttons = [...document.body.querySelectorAll('button')]
      .filter((found) => found.getAttribute('aria-pressed') !== null)

    expect(buttons.map((found) => found.textContent?.trim())).toEqual(['3', '4', '6', 'All'])
    expect(buttons.filter((found) => found.getAttribute('aria-pressed') === 'true'))
      .toHaveLength(1)
    expect(buttons[2].getAttribute('aria-pressed')).toBe('true')
  })

  /**
   * The sheet says what the count does on a wide screen rather than leaving the
   * artist to work out why it did nothing.
   */
  it('says the count applies to a phone', async () => {
    await mount(AdjustSheet, '/', {
      open: true,
      order: ['next', 'attention', 'money'],
      previewCount: 4,
    })

    expect(document.body.textContent).toContain('On a wide screen Attention takes the main column')
  })
})

describe('what it must not become', () => {
  /**
   * **It must not grow a switch that turns a block off.** A control that hides
   * an unheld Saturday is a control that gets left off, and Home is where that
   * would hurt most. The money period is absent for a different reason: one
   * value with two homes is two places to keep in step.
   */
  it('offers two settings and no more', async () => {
    await mount(AdjustSheet, '/', {
      open: true,
      order: ['next', 'attention', 'money'],
      previewCount: 4,
    })

    const panel = document.body.querySelector('[role="dialog"]')

    expect(panel?.querySelectorAll('h2')).toHaveLength(2)
    // No toggles, and no period selector.
    expect(panel?.querySelectorAll('input[type="checkbox"]')).toHaveLength(0)
    expect(panel?.textContent).not.toContain('This month')
    expect(panel?.textContent).not.toContain('Tax year')
  })
})
