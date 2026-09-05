import { nextTick, watch, type Ref } from 'vue'

// What every modal thing in the app does, written once: focus starts inside
// the panel, Tab cycles within it rather than leaving, Escape closes, and
// closing gives focus back to whatever opened it.
//
// This lives here rather than inside Sheet.vue because there is a second panel
// that needs it and cannot use Sheet: the month jump sheet is a bottom sheet
// on a phone and a panel anchored under a button whose position is measured at
// runtime on a wide screen, and Sheet's anchors are two fixed sidebar
// geometries switched on the lg viewport variant, where the calendar switches
// on a container query. Copying twenty lines of focus handling into the second
// panel is how two dialogs start behaving differently, so the behaviour moved
// instead. Sheet.vue is unchanged in what it does.

const selector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'

export interface DialogBehaviour {
  // Bind this to the panel's container as @keydown.
  onKeydown: (event: KeyboardEvent) => void
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
      await nextTick()

      const items = focusable()

      if (items.length > 0) {
        items[0].focus()
      } else {
        panel.value?.focus()
      }

      return
    }

    trigger?.focus()
    trigger = null
  }, { immediate: true })

  return { onKeydown }
}
