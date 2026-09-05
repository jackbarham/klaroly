<template>
  <Transition name="sheet">
    <div
      v-if="open"
      class="fixed inset-0 z-20"
      @keydown="onKeydown"
    >
      <!--
        The scrim. It is dark on a phone, where the sheet covers the screen,
        and invisible on a wide screen, where the panel is a small menu. It
        still catches a click either way, which is how clicking away closes
        the panel.
      -->
      <div
        class="absolute inset-0 bg-scrim lg:bg-transparent"
        @click="close"
      />

      <!--
        One panel, two presentations, switched on width alone. Below lg it is
        a sheet along the bottom edge with rounded top corners. At lg it is a
        small menu under the sidebar's New button: the sidebar is w-72 with
        page-top padding, an h-8 wordmark, a gap-8 and an h-12 button, so
        the button ends 136 pixels down and the menu starts at top-36.
      -->
      <div
        ref="panel"
        class="sheet-panel sheet-bottom absolute inset-x-0 bottom-0 rounded-t-sheet border-t border-border bg-surface-overlay px-4 pt-4 shadow-raised lg:inset-x-auto lg:bottom-auto lg:left-6 lg:top-36 lg:w-60 lg:rounded-sheet lg:border lg:pb-4"
        role="dialog"
        aria-modal="true"
        :aria-label="label"
        tabindex="-1"
      >
        <div
          class="mx-auto mb-4 h-1 w-10 rounded-full bg-border-strong lg:hidden"
          aria-hidden="true"
        />
        <slot />
      </div>
    </div>
  </Transition>
</template>

<script setup lang="ts">
// The presentational half of anything modal: the scrim, the panel, the way
// in and the way out. What goes inside is the caller's business.
//
// While it is open, focus starts inside the panel and stays there, Escape
// and the scrim close it, and closing puts focus back on whatever opened it.
import { nextTick, useTemplateRef, watch } from 'vue'

defineProps<{
  label: string
}>()

const open = defineModel<boolean>('open', { required: true })

const panel = useTemplateRef<HTMLElement>('panel')

// Whatever had focus when the panel opened, so that closing can give it back.
let trigger: HTMLElement | null = null

const selector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'

function focusable(): HTMLElement[] {
  if (!panel.value) {
    return []
  }

  return Array.from(panel.value.querySelectorAll<HTMLElement>(selector))
}

function close(): void {
  open.value = false
}

// Escape closes. Tab cycles inside the panel: from the last item forwards
// and from the first item backwards, focus wraps rather than leaving.
function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    close()

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
</script>

<style scoped>
/*
  The scrim fades and the panel slides up from below. Transform and opacity
  only, so the browser can do the whole thing on the compositor.
*/
.sheet-enter-active,
.sheet-leave-active {
  transition: opacity 200ms ease;
}

.sheet-enter-active .sheet-panel,
.sheet-leave-active .sheet-panel {
  transition: transform 200ms ease;
}

.sheet-enter-from,
.sheet-leave-to {
  opacity: 0;
}

.sheet-enter-from .sheet-panel,
.sheet-leave-to .sheet-panel {
  transform: translateY(100%);
}

@media (prefers-reduced-motion: reduce) {
  .sheet-enter-active,
  .sheet-leave-active,
  .sheet-enter-active .sheet-panel,
  .sheet-leave-active .sheet-panel {
    transition: none;
  }

  .sheet-enter-from .sheet-panel,
  .sheet-leave-to .sheet-panel {
    transform: none;
  }
}
</style>
