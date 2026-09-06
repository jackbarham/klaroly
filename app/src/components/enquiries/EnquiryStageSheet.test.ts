import { describe, expect, it } from 'vitest'
import { defineComponent, h, ref } from 'vue'
import { settle } from '@/lib/testHelpers'
import { mountWithCleanup } from '@/lib/testMount'
import EnquiryStageSheet from '@/components/enquiries/EnquiryStageSheet.vue'
import type { Enquiry, LostReason } from '@/types/enquiries'
import type { BookingStage } from '@/types/bookings'

// The stage change, which is the interaction the whole feature turns on, and
// the focus rule its two views need.

const mount = mountWithCleanup()

const open = ref(false)
const moves = ref<{ stage: BookingStage, reason: LostReason | null }[]>([])

const enquiry: Enquiry = {
  id: 7,
  stage: 'possible',
  client_name: 'Imogen Hartwell',
  contact_id: 10,
  source: 'web_form',
  source_booking: null,
  last_touched_at: new Date(2026, 8, 3, 12).toISOString(),
  waiting_on: null,
  total_minor: null,
  currency: 'GBP',
  event: null,
  has_trial: false,
  lost_reason: null,
  lost_side: null,
  clash: null,
}

const Host = defineComponent({
  setup: () => () => h(EnquiryStageSheet, {
    'enquiry': enquiry,
    'open': open.value,
    'onUpdate:open': (value: boolean) => { open.value = value },
    'onMove': (_record: Enquiry, stage: BookingStage, reason: LostReason | null) => {
      moves.value.push({ stage, reason })
    },
  }),
})

async function opened(): Promise<void> {
  open.value = false
  moves.value = []

  await mount(Host, '/enquiries')
  await settle()

  open.value = true
  await settle()
}

function panel(): HTMLElement | null {
  return document.querySelector<HTMLElement>('[role="dialog"]')
}

function buttonSaying(text: string): HTMLButtonElement | undefined {
  return [...document.querySelectorAll<HTMLButtonElement>('[role="dialog"] button')]
    .find((button) => button.textContent?.includes(text))
}

describe('the first view', () => {
  // Four stages, a rule, and the two ways an enquiry stops being one.
  it('offers the four live stages, Convert and the ending', async () => {
    await opened()

    const text = panel()?.textContent ?? ''

    for (const label of ['New', 'In conversation', 'Possible', 'Quoted']) {
      expect(text).toContain(label)
    }

    expect(text).toContain('Convert to booking')
    expect(text).toContain('This one is not going ahead')
    expect(panel()?.querySelector('hr')).not.toBeNull()
  })

  it('moves the enquiry and closes', async () => {
    await opened()

    buttonSaying('Quoted')?.click()
    await settle()

    expect(moves.value).toEqual([{ stage: 'quoted', reason: null }])
    expect(open.value).toBe(false)
  })

  /**
   * Tapping the stage it is already at writes nothing. It would move
   * last_touched_at, and the top of a list ordered by neglect is not somewhere
   * to lose a row by looking at it.
   */
  it('writes nothing when the stage is the one it is already at', async () => {
    await opened()

    buttonSaying('Possible')?.click()
    await settle()

    expect(moves.value).toEqual([])
    expect(open.value).toBe(false)
  })
})

describe('the second view', () => {
  /**
   * Nine reasons under two headings. The heading carries the side, so no
   * reason has to name who did it, and the two rows both reading "Another
   * reason" are not a duplication: which heading a reason sits under is the
   * fact being recorded.
   */
  it('lists nine reasons under two headings', async () => {
    await opened()

    buttonSaying('This one is not going ahead')?.click()
    await settle()

    const text = panel()?.textContent ?? ''

    expect(text).toContain('They ended it')
    expect(text).toContain('You turned it down')
    expect(text).toContain('Why is it not going ahead?')

    const reasons = [...document.querySelectorAll('[role="dialog"] button')]
      .filter((button) => button.textContent?.trim() !== 'Back')

    expect(reasons).toHaveLength(9)
    expect(reasons.filter((button) => button.textContent?.trim() === 'Another reason')).toHaveLength(2)
  })

  it('ends the enquiry with the reason that was tapped', async () => {
    await opened()

    buttonSaying('This one is not going ahead')?.click()
    await settle()

    buttonSaying('Already booked that day')?.click()
    await settle()

    expect(moves.value).toEqual([{ stage: 'lost', reason: 'already_booked' }])
  })

  /**
   * **The focus rule, which the prototype found the hard way.**
   *
   * Going back from the reasons view hides the Back button. If that button had
   * focus, focus falls to the body, the panel's keydown handler stops seeing
   * anything, and Escape silently stops closing the sheet. The sheet calls
   * AnchoredSheet's refocus() after each swap, which is useDialogBehaviour's
   * own, so the mechanism stays in one place and the decision stays with the
   * only thing that knows its contents changed.
   */
  it('keeps focus inside the panel when the view swaps, so Escape still closes', async () => {
    await opened()

    buttonSaying('This one is not going ahead')?.click()
    await settle()

    expect(panel()?.contains(document.activeElement)).toBe(true)

    // Back, which is the button that had focus and is about to be removed.
    const back = buttonSaying('Back')

    back?.focus()
    back?.click()
    await settle()

    expect(document.activeElement).not.toBe(document.body)
    expect(panel()?.contains(document.activeElement)).toBe(true)

    // And the whole point of it: Escape still reaches the handler.
    panel()?.parentElement?.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    await settle()

    expect(open.value).toBe(false)
  })

  // Opening it again starts on the stages, so an ending abandoned last time
  // does not reappear as the first thing the next enquiry offers.
  it('starts on the stages again the next time it opens', async () => {
    await opened()

    buttonSaying('This one is not going ahead')?.click()
    await settle()

    open.value = false
    await settle()
    open.value = true
    await settle()

    expect(panel()?.textContent).toContain('Convert to booking')
  })
})
