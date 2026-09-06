<template>
  <Teleport to="body">
    <Transition name="anchored">
      <div
        v-if="open"
        class="fixed inset-0 z-20"
        @keydown="onKeydown"
      >
        <!--
          The scrim. It is dark below lg, where the panel covers the bottom of
          the screen, and invisible at lg, where it is a small menu. It still
          catches a click either way, which is how clicking away closes it.
        -->
        <div
          class="absolute inset-0 bg-scrim lg:bg-transparent"
          @click="open = false"
        />

        <div
          ref="panel"
          class="anchored__panel absolute inset-x-0 bottom-0 rounded-t-sheet border-t border-border bg-surface-overlay px-4 pt-2 shadow-raised sheet-bottom lg:inset-auto lg:rounded-card lg:border lg:p-4 lg:shadow-menu"
          :class="[widthClass, align === 'left' ? 'is-left' : 'is-right']"
          :style="panelStyle"
          role="dialog"
          aria-modal="true"
          :aria-label="label"
          tabindex="-1"
        >
          <div
            class="mx-auto mb-3 h-1 w-10 rounded-full bg-border-strong lg:hidden"
            aria-hidden="true"
          />

          <slot />
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
// A bottom sheet below lg and a panel hanging under a measured button at lg
// and up. The month jump sheet and the contacts view menu are both this, and
// the enquiries screen brings two more.
//
// **It is not ui/Sheet.vue and it must not become it.** Sheet's desktop anchor
// is a closed set of two fixed sidebar geometries, written as classes and
// carrying the sums in a comment. This hangs off a rectangle measured at
// runtime, which is the whole reason the two are separate components rather
// than one with a flag. What they do share, the focus trap, Escape, the scrim
// and giving focus back to the trigger, is useDialogBehaviour in
// src/lib/dialog.ts, which all three call, so there is one definition of how a
// dialog behaves rather than three that drift.
//
// The measuring is in here on purpose. Two callers writing their own
// getBoundingClientRect is what this component exists to stop.
import { nextTick, ref, useTemplateRef, watch } from 'vue'
import { useDialogBehaviour } from '@/lib/dialog'

const props = defineProps<{
  // The panel's accessible name, already resolved by the caller: this
  // component names nothing itself.
  label: string
  // The button this hangs under at lg and up. Null below lg, where the panel
  // is a bottom sheet and there is nothing to hang from.
  anchorTo?: HTMLElement | null
  // Which of the trigger's vertical edges the panel is pinned to at lg, so
  // that it grows away from that edge rather than off the screen. The month
  // jump hangs off a title near the left of the calendar and grows right; the
  // contacts view menu hangs off a button at the right of a 400px column and
  // grows left, so that three hundred pixels of panel stay over the list
  // rather than spilling across the detail.
  //
  // **This is not a placement variant.** A variant is a caller's choice about
  // appearance and it multiplies; this is a fact about where the trigger sits,
  // in the same category as anchorTo itself. anchorTo says which rectangle and
  // this says which of its two vertical edges, and there is no third value it
  // could grow.
  //
  // It is deliberately not inferred from the trigger's position. Choosing the
  // edge that keeps the panel on screen would silently move an existing caller
  // at some widths, and it cannot be tested deterministically without stubbing
  // the viewport as well as the rectangle. The moment to revisit that is a
  // caller that cannot answer the question, not simply a fourth caller.
  align: 'left' | 'right'
  /**
   * The panel's width at lg, as a Tailwind width utility and nothing else:
   * `lg:w-75`, `lg:w-80`, `lg:w-88`. Below lg the panel is the full width of
   * the viewport, so it only ever applies to the anchored presentation.
   *
   * Required rather than defaulted, because the callers are split between
   * widths and a default would promote one of them to a rule by accident.
   *
   * **A string rather than a named set, and that is settled.** Four callers
   * want three widths, and each is an independent constraint rather than a
   * taste: the month jump needs 320 because three 96px month cells plus their
   * gaps come to it, the two view menus need 300 to stay over a 400px list
   * column, and the stage sheet needs 352 because its rows carry a second line
   * of explanation. Collapsing them would mean failing one of those
   * constraints to tidy a prop, and naming all three would be the size scale
   * this component deliberately does not have.
   *
   * The signal to revisit is not a fifth caller. It is a fifth caller wanting
   * a FOURTH width, which would mean the panel is being asked to be more than
   * one thing.
   */
  widthClass: string
}>()

const open = defineModel<boolean>('open', { required: true })

const panel = useTemplateRef<HTMLElement>('panel')

// Escape closes, Tab cycles inside the panel, and closing gives focus back to
// whatever opened it.
const { onKeydown, refocus } = useDialogBehaviour(panel, open)

// For a caller that swaps what is inside the panel while it is open. It owns
// the composable, so it is the only thing that can hand this on, and a caller
// finding its own first focusable element would be a second copy of the one
// interesting line in useDialogBehaviour. See the note there.
defineExpose({ refocus })

// The gap between the trigger's bottom edge and the top of the panel, in
// pixels, which is what docs/style-guide.md asks for between a menu and the
// control that opens it. It is a number rather than a token because it is
// measured against a rectangle in JavaScript, and it is two spacing steps.
const gap = 8

// Where the panel sits at lg and up. The panel is fixed and teleported to the
// body, so these are viewport coordinates and nothing between here and the
// root can shift them.
//
// Only the two properties `align` needs are written, and the stylesheet reads
// them through a class rather than through an unset variable falling back to
// auto. The fallback works and is exactly the sort of trick that has to be
// known rather than read.
const panelStyle = ref<Record<string, string>>({})

watch(open, async (isOpen) => {
  if (!isOpen) {
    return
  }

  await nextTick()

  const trigger = props.anchorTo

  if (!trigger) {
    return
  }

  const anchor = trigger.getBoundingClientRect()
  const top = `${Math.round(anchor.bottom + gap)}px`

  panelStyle.value = props.align === 'left'
    ? { '--menu-top': top, '--menu-left': `${Math.round(anchor.left)}px` }
    : { '--menu-top': top, '--menu-right': `${Math.round(window.innerWidth - anchor.right)}px` }
})
</script>

<style scoped>
/*
  The scrim fades and the panel moves. Transform and opacity only, so the whole
  thing stays on the compositor, and both directions run at --duration-base on
  the app's one curve: no component in this codebase writes a duration.
*/
.anchored-enter-active,
.anchored-leave-active {
  transition: opacity var(--duration-base) var(--ease-out);
}

.anchored-enter-active .anchored__panel,
.anchored-leave-active .anchored__panel {
  transition: transform var(--duration-base) var(--ease-out);
}

.anchored-enter-from,
.anchored-leave-to {
  opacity: 0;
}

/* Below lg it is a sheet and travels its own height up from the bottom edge,
   which is the one place a long move is right. */
.anchored-enter-from .anchored__panel,
.anchored-leave-to .anchored__panel {
  transform: translateY(100%);
}

/* At lg the panel hangs under its trigger, at the position measured when it
   opened, and comes out of that trigger rather than up from the floor. The
   query is written the way Sheet.vue writes its own, in rem against the same
   breakpoint token. */
@media (width >= 64rem) {
  .anchored__panel {
    top: var(--menu-top);
  }

  .anchored__panel.is-left {
    left: var(--menu-left);
    transform-origin: top left;
  }

  .anchored__panel.is-right {
    right: var(--menu-right);
    transform-origin: top right;
  }

  .anchored-enter-from .anchored__panel,
  .anchored-leave-to .anchored__panel {
    transform: translateY(calc(var(--spacing) * -4));
  }
}

@media (prefers-reduced-motion: reduce) {
  .anchored-enter-active,
  .anchored-leave-active,
  .anchored-enter-active .anchored__panel,
  .anchored-leave-active .anchored__panel {
    transition: none;
  }

  .anchored-enter-from .anchored__panel,
  .anchored-leave-to .anchored__panel {
    transform: none;
  }
}
</style>
