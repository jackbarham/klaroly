import { beforeEach, describe, expect, it } from 'vitest'
import { defineComponent, h, ref } from 'vue'
import { settle } from '@/lib/testHelpers'
import { mountWithCleanup } from '@/lib/testMount'
import AnchoredSheet from '@/components/ui/AnchoredSheet.vue'

// Only what the panel itself owns. What goes inside it, and when it opens, are
// the caller's, and MonthJumpSheet's own tests cover those.

const mount = mountWithCleanup()

// The open state and the props under test, read by the one host component
// below. One component rather than one per test: a test file that defines
// several is a file where two of them quietly differ.
const isOpen = ref(false)
const given = ref<Record<string, unknown>>({})
const wrapped = ref(false)

const Host = defineComponent({
  setup() {
    return () => {
      const sheet = h(
        AnchoredSheet,
        {
          'label': 'A panel',
          'align': 'left',
          'widthClass': 'lg:w-80',
          ...given.value,
          'open': isOpen.value,
          'onUpdate:open': (value: boolean) => { isOpen.value = value },
        },
        { default: () => h('button', { type: 'button' }, 'Inside') },
      )

      // The teleport test needs a parent to prove the panel does not land in
      // it. Everything else mounts the sheet on its own.
      return wrapped.value ? h('div', { class: 'host' }, [sheet]) : sheet
    }
  },
})

// A trigger in the document with a rectangle of its own, because the panel's
// position is measured from one and the test environment gives every element a
// zero-sized box.
function triggerWith(rect: Partial<DOMRect>): HTMLButtonElement {
  const button = document.createElement('button')

  button.textContent = 'Open'
  document.body.appendChild(button)

  button.getBoundingClientRect = () => ({
    top: 0, left: 0, right: 0, bottom: 0, width: 0, height: 0, x: 0, y: 0,
    toJSON: () => ({}),
    ...rect,
  } as DOMRect)

  return button
}

// Mount the host closed, then open it, which is the sequence the measuring
// watcher needs: it runs on the change rather than on the initial value.
async function open(props: Record<string, unknown> = {}): Promise<void> {
  given.value = props

  await mount(Host, '/contacts')
  await settle()

  isOpen.value = true
  await settle()
}

function panel(): HTMLElement | null {
  return document.querySelector<HTMLElement>('[role="dialog"]')
}

beforeEach(() => {
  isOpen.value = false
  given.value = {}
  wrapped.value = false
})

it('renders nothing until it is opened', async () => {
  await mount(Host, '/contacts')
  await settle()

  expect(panel()).toBeNull()

  isOpen.value = true
  await settle()

  expect(panel()).not.toBeNull()
})

// Teleported to the body, because container-type on an ancestor makes that
// element the containing block for its fixed descendants and the scrim would
// otherwise stop at the page's edges.
it('teleports the panel to the body rather than into its parent', async () => {
  wrapped.value = true

  await open()

  expect(panel()).not.toBeNull()
  expect(document.querySelector('.host [role="dialog"]')).toBeNull()
})

it('takes its accessible name and its width from the caller', async () => {
  await open({ widthClass: 'lg:w-75', label: 'The view menu' })

  expect(panel()?.getAttribute('aria-label')).toBe('The view menu')
  expect(panel()?.className).toContain('lg:w-75')
})

describe('closing', () => {
  it('closes on Escape', async () => {
    await open()

    panel()?.parentElement?.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    await settle()

    expect(isOpen.value).toBe(false)
  })

  it('closes on a click outside the panel', async () => {
    await open()

    const scrim = panel()?.previousElementSibling as HTMLElement

    scrim.dispatchEvent(new Event('click', { bubbles: true }))
    await settle()

    expect(isOpen.value).toBe(false)
  })
})

describe('focus', () => {
  it('starts inside the panel', async () => {
    await open()

    expect(document.activeElement?.textContent).toBe('Inside')
  })

  // Paired with the assertion above, so "focus is somewhere" cannot pass for
  // both halves: it has to leave the trigger and come back to it.
  it('goes back to whatever opened it', async () => {
    const trigger = triggerWith({ bottom: 100, left: 40, right: 200 })

    trigger.focus()

    await open({ anchorTo: trigger })

    expect(document.activeElement).not.toBe(trigger)

    isOpen.value = false
    await settle()

    expect(document.activeElement).toBe(trigger)
  })
})

/**
 * The measuring, which is the whole reason this is a component rather than a
 * convention: two callers writing their own getBoundingClientRect is what it
 * exists to stop.
 *
 * Each alignment writes only the two properties it needs, and the absence of
 * the third is asserted beside the presence of the other two. A panel that
 * wrote both edges would pin its width to the trigger's, which is not what
 * either caller wants.
 */
describe('the anchor measurement', () => {
  it('pins the left edge and writes the top, when aligned left', async () => {
    const trigger = triggerWith({ bottom: 100, left: 40, right: 200 })

    await open({ anchorTo: trigger, align: 'left' })

    const style = panel()!.style

    // The trigger's bottom plus the eight the style guide asks for between a
    // menu and the control that opens it.
    expect(style.getPropertyValue('--menu-top')).toBe('108px')
    expect(style.getPropertyValue('--menu-left')).toBe('40px')
    expect(style.getPropertyValue('--menu-right')).toBe('')
  })

  it('pins the right edge against the viewport, when aligned right', async () => {
    const trigger = triggerWith({ bottom: 64, left: 600, right: 760 })

    await open({ anchorTo: trigger, align: 'right' })

    const style = panel()!.style

    expect(style.getPropertyValue('--menu-top')).toBe('72px')
    // Measured from the right of the window, because that is what the right
    // property is relative to.
    expect(style.getPropertyValue('--menu-right')).toBe(`${window.innerWidth - 760}px`)
    expect(style.getPropertyValue('--menu-left')).toBe('')
  })

  it('rounds a fractional rectangle to whole pixels', async () => {
    const trigger = triggerWith({ bottom: 100.4, left: 39.6, right: 200 })

    await open({ anchorTo: trigger, align: 'left' })

    const style = panel()!.style

    expect(style.getPropertyValue('--menu-top')).toBe('108px')
    expect(style.getPropertyValue('--menu-left')).toBe('40px')
  })

  // Below lg there is nothing to hang from: the panel is a bottom sheet, and
  // the custom properties are only read inside the lg media query anyway.
  it('writes nothing when there is no anchor', async () => {
    await open()

    const style = panel()!.style

    expect(style.getPropertyValue('--menu-top')).toBe('')
    expect(style.getPropertyValue('--menu-left')).toBe('')
    expect(style.getPropertyValue('--menu-right')).toBe('')
  })
})
