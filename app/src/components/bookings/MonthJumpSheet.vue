<template>
  <Teleport to="body">
    <Transition name="jump">
      <div
        v-if="open"
        class="fixed inset-0 z-20"
        @keydown="onKeydown"
      >
        <div
          class="absolute inset-0 bg-scrim lg:bg-transparent"
          @click="open = false"
        />

        <!--
          A bottom sheet on a phone and a panel hanging under the month title
          on a wide screen, switched at lg and nowhere else. That is the same
          rule and the same breakpoint ui/Sheet.vue uses, deliberately: this is
          a viewport overlay rather than part of the page's layout, so it
          answers the window rather than the container query that decides
          whether the calendar sits above the list or beside it.
        -->
        <div
          ref="panel"
          class="jump__panel absolute inset-x-0 bottom-0 rounded-t-sheet border-t border-border bg-surface-overlay px-4 pt-2 pb-5 shadow-raised sheet-bottom lg:inset-auto lg:w-80 lg:rounded-card lg:border lg:p-4 lg:shadow-menu"
          :style="panelStyle"
          role="dialog"
          aria-modal="true"
          :aria-label="t('bookings.calendar.jump_label')"
          tabindex="-1"
        >
          <div
            class="mx-auto mb-3 h-1 w-10 rounded-full bg-border-strong lg:hidden"
            aria-hidden="true"
          />

          <!--
            The year strip. Its own scrollLeft is set to centre the current
            year, never scrollIntoView, which walks every scrollable ancestor
            including the document and would drag the page down behind the
            sheet.
          -->
          <div
            ref="strip"
            class="jump__years mb-3 flex gap-1.5 overflow-x-auto"
            role="group"
            :aria-label="t('bookings.calendar.jump_years_label')"
          >
            <button
              v-for="year in years"
              :key="year"
              class="h-11 min-w-14 flex-none rounded-control px-3 text-body font-medium tabular-nums transition-colors focus-visible:focus-ring"
              :class="year === shownYear ? 'bg-accent-subtle text-accent-text' : 'bg-surface-sunken text-text-muted hover:bg-surface-hover'"
              type="button"
              :aria-pressed="year === shownYear"
              @click="shownYear = year"
            >
              {{ year }}
            </button>
          </div>

          <div
            class="grid grid-cols-3 gap-2"
            role="group"
            :aria-label="t('bookings.calendar.jump_months_label')"
          >
            <button
              v-for="(name, index) in monthNames"
              :key="name"
              class="relative h-12 rounded-control text-body transition-colors focus-visible:focus-ring"
              :class="monthClasses(index)"
              type="button"
              :aria-label="monthLabel(name, index)"
              :aria-current="isShown(index) ? 'true' : undefined"
              @click="choose(index)"
            >
              {{ name }}
              <!--
                A dot on every month that has something in it. An artist's
                diary is mostly empty with clusters on summer Saturdays, so
                "where is my work" is a more common question than "take me to
                March", and that is the part a stock date picker cannot answer.
              -->
              <span
                v-if="hasWork(index)"
                class="absolute bottom-1.5 left-1/2 h-1 w-1 -translate-x-1/2 rounded-full"
                :class="isShown(index) ? 'bg-text-on-accent' : 'bg-accent'"
                aria-hidden="true"
              />
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
// Two taps to any month in any year: pick a year from the strip, then a month
// from the grid.
//
// It does not use ui/Sheet.vue, and the reason is worth writing down so nobody
// merges the two later: Sheet's anchor is a closed set of two fixed sidebar
// geometries, and this panel hangs off a button whose position depends on how
// long the month's name is and has to be measured. What the two do share, the
// focus trap, Escape, the scrim and returning focus to the trigger, is
// useDialogBehaviour in src/lib/dialog.ts, which both call, so there is one
// definition of how a dialog behaves rather than two that drift.
import { computed, nextTick, ref, useTemplateRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { format, startOfMonth } from 'date-fns'
import { useDialogBehaviour } from '@/lib/dialog'

const props = defineProps<{
  month: Date
  // 'YYYY-MM' for every month carrying at least one event.
  monthsWithWork: ReadonlySet<string>
  // The button this hangs under at lg and up.
  anchorTo?: HTMLElement | null
}>()

const emit = defineEmits<{
  select: [month: Date]
}>()

const { t } = useI18n()

const open = defineModel<boolean>('open', { required: true })

const panel = useTemplateRef<HTMLElement>('panel')
const strip = useTemplateRef<HTMLElement>('strip')

const { onKeydown } = useDialogBehaviour(panel, open)

// Formatted rather than listed, so these are the same month names the header
// and the rows use and there is no second spelling of September in the app.
const monthNames = computed(() => {
  const pattern = t('bookings.calendar.format.month_short')

  return Array.from({ length: 12 }, (unused, index) => format(new Date(2000, index, 1), pattern))
})

// Far enough back to find last season's work and far enough forward for a
// wedding booked three years out, which does happen.
const years = computed(() => {
  const current = new Date().getFullYear()

  return Array.from({ length: 6 }, (unused, index) => current - 1 + index)
})

const shownYear = ref(props.month.getFullYear())

function isShown(index: number): boolean {
  return shownYear.value === props.month.getFullYear() && index === props.month.getMonth()
}

function hasWork(index: number): boolean {
  return props.monthsWithWork.has(`${shownYear.value}-${String(index + 1).padStart(2, '0')}`)
}

function monthLabel(name: string, index: number): string {
  const full = `${name} ${shownYear.value}`

  return hasWork(index) ? t('bookings.calendar.jump_has_work', { month: full }) : full
}

function monthClasses(index: number): string {
  if (isShown(index)) {
    return 'bg-accent font-medium text-text-on-accent'
  }

  return 'bg-surface-sunken text-text hover:bg-surface-hover'
}

function choose(index: number): void {
  emit('select', startOfMonth(new Date(shownYear.value, index, 1)))
  open.value = false
}

// Where the panel sits at lg and up. The panel is fixed and teleported to the
// body, so these are viewport coordinates and nothing between here and the
// root can shift them.
const panelStyle = ref<Record<string, string>>({})

watch(open, async (isOpen) => {
  if (!isOpen) {
    return
  }

  shownYear.value = props.month.getFullYear()

  await nextTick()

  const trigger = props.anchorTo

  if (trigger) {
    const anchor = trigger.getBoundingClientRect()

    panelStyle.value = {
      '--jump-top': `${Math.round(anchor.bottom + 6)}px`,
      '--jump-left': `${Math.round(anchor.left)}px`,
    }
  }

  const active = strip.value?.querySelector<HTMLElement>('[aria-pressed="true"]')

  if (strip.value && active) {
    strip.value.scrollLeft = active.offsetLeft - (strip.value.clientWidth - active.offsetWidth) / 2
  }
})
</script>

<style scoped>
/*
  The scrim fades and the panel moves. Transform and opacity only, so the whole
  thing stays on the compositor, and both directions run at --duration-base on
  the app's one curve: no component in this codebase writes a duration.
*/
.jump-enter-active,
.jump-leave-active {
  transition: opacity var(--duration-base) var(--ease-out);
}

.jump-enter-active .jump__panel,
.jump-leave-active .jump__panel {
  transition: transform var(--duration-base) var(--ease-out);
}

.jump-enter-from,
.jump-leave-to {
  opacity: 0;
}

/* Below lg it is a sheet and travels its own height up from the bottom edge,
   which is the one place a long move is right. */
.jump-enter-from .jump__panel,
.jump-leave-to .jump__panel {
  transform: translateY(100%);
}

.jump__years {
  scrollbar-width: none;
}

.jump__years::-webkit-scrollbar {
  display: none;
}

/* At lg the panel hangs under the month title, at the position measured when
   it opened, and comes out of the button rather than up from the floor. The
   query is written the way Sheet.vue writes its own, in rem against the same
   breakpoint token. */
@media (width >= 64rem) {
  .jump__panel {
    top: var(--jump-top);
    left: var(--jump-left);
    transform-origin: top left;
  }

  .jump-enter-from .jump__panel,
  .jump-leave-to .jump__panel {
    transform: translateY(calc(var(--spacing) * -1.5));
  }
}

@media (prefers-reduced-motion: reduce) {
  .jump-enter-active,
  .jump-leave-active,
  .jump-enter-active .jump__panel,
  .jump-leave-active .jump__panel {
    transition: none;
  }

  .jump-enter-from .jump__panel,
  .jump-leave-to .jump__panel {
    transform: none;
  }
}
</style>
