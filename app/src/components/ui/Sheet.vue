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
        a sheet along the bottom edge, with the sheet radius on its top
        corners and the raised shadow. At lg it is a small menu in the
        sidebar's column, with the card radius, because a menu that size looks
        like a box rather than a sheet, and the menu shadow, whose first layer
        is the hairline ring around it. Which of the two heights it opens at
        is in anchorClasses.
      -->
      <div
        ref="panel"
        class="sheet-panel sheet-bottom absolute inset-x-0 bottom-0 rounded-t-sheet border-t border-border bg-surface-overlay px-4 pt-4 shadow-raised lg:inset-x-auto lg:left-6 lg:w-60 lg:rounded-card lg:border-0 lg:p-3 lg:shadow-menu"
        :class="[anchorClasses[anchor], originClasses[anchor]]"
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

<script lang="ts">
// A row inside the panel, which is what every menu built on this is made of.
// It is here rather than copied into each menu because the second copy is
// where two menus start drifting apart.
//
// It is a sidebar item at lg, where the menu opens beside one: the same
// height, the same gap, the same type at the same weight, and the same 4px
// between rows.
// On a phone the panel is a bottom sheet and the row is taller, because 40px
// is under the tap minimum.
//
// The row is a group so that the icon can follow the label into the accent on
// hover: an icon left grey beside a purple label reads as two things.
export const sheetListClasses = 'space-y-1'

export const sheetRowClasses = 'group flex h-14 w-full items-center gap-3 rounded-control px-4 text-left text-body font-medium text-text-strong transition-colors hover:bg-surface-sunken hover:text-accent-text focus-visible:focus-ring lg:h-10'

// The icon in that row, which is the sidebar's idle icon at the sidebar's size.
export const sheetRowIconClasses = 'h-6 w-6 text-text-placeholder transition-colors group-hover:text-accent-text'
</script>

<script setup lang="ts">
// The presentational half of anything modal: the scrim, the panel, the way
// in and the way out. What goes inside is the caller's business.
//
// While it is open, focus starts inside the panel and stays there, Escape
// and the scrim close it, and closing puts focus back on whatever opened it.
import { nextTick, useTemplateRef, watch } from 'vue'

// The anchor only means anything at lg, where the panel is a menu in the
// sidebar's column rather than a sheet along the bottom of a phone.
withDefaults(defineProps<{
  label: string
  anchor?: 'below-new' | 'above-account'
}>(), {
  anchor: 'below-new',
})

// Both positions are measured off the sidebar, so a change to its geometry
// is a change here as well. See AppSidebar.vue, which carries the same sums.
//
// Below the New button: page-top padding, an h-8 wordmark, a gap-8 and an
// h-12 button put the button's bottom 136 pixels down, so the menu starts at
// top-36.
//
// Above the account row: the column's pb-6 and the row's own h-10 come to 64
// pixels, and the 8 the style guide asks for above the trigger puts the
// menu's bottom at bottom-18.
const anchorClasses = {
  'below-new': 'lg:bottom-auto lg:top-36',
  'above-account': 'lg:bottom-18 lg:top-auto',
}

// At lg the panel comes out of the thing that opened it and goes back into
// it: down from under the New button, up from above the account row. These
// are plain class names rather than utilities because the transition that
// reads them is in the style block below. Below lg neither applies: both
// panels are the same bottom sheet, arriving from the bottom edge.
const originClasses = {
  'below-new': 'from-above',
  'above-account': '',
}

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
  The scrim fades and the panel slides. Transform and opacity only, so the
  browser can do the whole thing on the compositor.

  Below lg it is a bottom sheet and travels its own height up from the bottom
  edge. At lg it is a menu and travels one spacing step out of its trigger,
  which is a much smaller move: the panel is already beside the button, so
  anything longer sweeps it across the sidebar rather than dropping it out of
  the thing that was clicked.
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

/* A menu that opens below its trigger drops down into place and lifts back
   into it. One that opens above does the opposite, which is what the rule
   above already does at a shorter distance. */
@media (width >= 64rem) {
  .sheet-enter-from .sheet-panel,
  .sheet-leave-to .sheet-panel {
    transform: translateY(calc(var(--spacing) * 4));
  }

  .sheet-enter-from .sheet-panel.from-above,
  .sheet-leave-to .sheet-panel.from-above {
    transform: translateY(calc(var(--spacing) * -4));
  }
}

@media (prefers-reduced-motion: reduce) {
  .sheet-enter-active,
  .sheet-leave-active,
  .sheet-enter-active .sheet-panel,
  .sheet-leave-active .sheet-panel {
    transition: none;
  }

  .sheet-enter-from .sheet-panel,
  .sheet-leave-to .sheet-panel,
  .sheet-enter-from .sheet-panel.from-above,
  .sheet-leave-to .sheet-panel.from-above {
    transform: none;
  }
}
</style>
