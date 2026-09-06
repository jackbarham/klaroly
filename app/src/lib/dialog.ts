import { nextTick, watch, type Ref } from 'vue'

// What every modal thing in the app does, written once: focus starts inside
// the panel, Tab cycles within it rather than leaving, Escape closes, and
// closing gives focus back to whatever opened it.
//
// This lives here rather than inside Sheet.vue because there are two panel
// components rather than one, and neither can be the other. Sheet's desktop
// anchor is a closed set of two fixed sidebar geometries, written as classes
// with the sums in a comment. ui/AnchoredSheet.vue hangs off a rectangle
// measured at runtime, which is what the month jump sheet and the contacts
// view menu both need and what Sheet cannot express.
//
// That was true when this file was written for one panel that could not use
// Sheet, and it is still true now that the panel has become a component with
// callers of its own: the reason the two cannot merge is the measurement, and
// extracting AnchoredSheet did not remove it. What all three do share is this,
// because copying twenty lines of focus handling into a second component is
// how two dialogs start behaving differently. Sheet.vue is unchanged in what
// it does.

const selector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'

export interface DialogBehaviour {
  // Bind this to the panel's container as @keydown.
  onKeydown: (event: KeyboardEvent) => void
  // Put focus back on the first thing in the panel, for a panel that swaps its
  // own contents while it is open. See the note on the function below.
  refocus: () => Promise<void>
}

export function useDialogBehaviour(
  panel: Ref<HTMLElement | null>,
  open: Ref<boolean>,
): DialogBehaviour {
  // Whatever had focus when the panel opened, so that closing can give it back.
  let trigger: HTMLElement | null = null

  function focusable(): HTMLElement[] {
    if (!panel.value) {
      return []
    }

    return Array.from(panel.value.querySelectorAll<HTMLElement>(selector))
  }

  /**
   * Focus the first focusable thing in the panel, or the panel itself.
   *
   * Called on open, and callable again by a panel that replaces what is inside
   * it. The enquiries stage sheet is two views in one panel, and going back
   * from the second hides the button that was focused: focus then falls to the
   * body, the keydown handler on the panel stops seeing anything, and Escape
   * silently stops closing the sheet.
   *
   * **It is exposed rather than triggered from in here.** Watching the panel's
   * contents would mean this file guessing when a caller meant to move focus,
   * and a caller doing its own element-finding would be a second copy of the
   * only interesting line in it. So the mechanism stays here and the decision
   * stays with the only thing that knows its content changed.
   */
  async function refocus(): Promise<void> {
    await nextTick()

    const items = focusable()

    if (items.length > 0) {
      items[0].focus()

      return
    }

    panel.value?.focus()
  }

  function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
      open.value = false

      return
    }

    if (event.key !== 'Tab') {
      return
    }

    const items = focusable()

    if (items.length === 0) {
      event.preventDefault()

      return
    }

    const first = items[0]
    const last = items[items.length - 1]
    const active = document.activeElement

    if (event.shiftKey && (active === first || active === panel.value)) {
      event.preventDefault()
      last.focus()
    } else if (!event.shiftKey && active === last) {
      event.preventDefault()
      first.focus()
    }
  }

  watch(open, async (isOpen) => {
    if (isOpen) {
      trigger = document.activeElement instanceof HTMLElement ? document.activeElement : null

      await refocus()

      return
    }

    trigger?.focus()
    trigger = null
  }, { immediate: true })

  return { onKeydown, refocus }
}
