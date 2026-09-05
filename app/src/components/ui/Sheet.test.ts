import { afterEach, describe, expect, it } from 'vitest'
import { defineComponent, h, nextTick, ref } from 'vue'
import Sheet from '@/components/ui/Sheet.vue'
import { element } from '@/lib/testHelpers'
import { mountWithCleanup, type Mounted } from '@/lib/testMount'

// Whether the sheet below is open. It sits out here so that a test can read
// it: a sheet is controlled by whoever opens it, so closing means this going
// back to false. The element leaving the page afterwards is Vue's transition,
// which needs a real browser to finish.
const open = ref(false)

// A button that opens a sheet with one button inside it: the smallest thing
// that can show whether focus goes in and comes back out again.
const Host = defineComponent({
  setup() {
    return () => h('div', [
      h('button', {
        id: 'trigger',
        onClick: () => {
          open.value = true
        },
      }, 'Open'),
      h(Sheet, {
        'label': 'Create',
        'open': open.value,
        'onUpdate:open': (value: boolean) => {
          open.value = value
        },
      }, {
        default: () => h('button', { id: 'inside' }, 'A row'),
      }),
    ])
  },
})

const mount = mountWithCleanup()

async function openSheet(): Promise<Mounted> {
  const result = await mount(Host, '/')
  const trigger = element(result.host, '#trigger')

  trigger.focus()
  trigger.click()
  await nextTick()
  await nextTick()

  return result
}

afterEach(() => {
  open.value = false
})

describe('the sheet', () => {
  it('opens, and puts focus on the first thing inside it', async () => {
    const mounted = await openSheet()

    expect(open.value).toBe(true)
    expect(mounted.host.querySelector('[role="dialog"]')).not.toBeNull()
    expect(document.activeElement?.id).toBe('inside')
  })

  it('closes on Escape and gives focus back to whatever opened it', async () => {
    const mounted = await openSheet()

    element(mounted.host, '[role="dialog"]').dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    await nextTick()
    await nextTick()

    expect(open.value).toBe(false)
    expect(document.activeElement?.id).toBe('trigger')
  })

  it('closes when the scrim is clicked', async () => {
    const mounted = await openSheet()

    const scrim = element(mounted.host, '[role="dialog"]').previousElementSibling

    expect(scrim).not.toBeNull()
    scrim?.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await nextTick()
    await nextTick()

    expect(open.value).toBe(false)
    expect(document.activeElement?.id).toBe('trigger')
  })

  it('keeps Tab inside the panel', async () => {
    const mounted = await openSheet()

    const dialog = element(mounted.host, '[role="dialog"]')

    // There is one thing to focus, so tabbing forwards from it wraps back to
    // it rather than moving on to the page behind the sheet.
    dialog.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', bubbles: true }))
    await nextTick()

    expect(document.activeElement?.id).toBe('inside')
  })
})
