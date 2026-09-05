<template>
  <section
    class="cal select-none"
    :aria-label="t('bookings.calendar.label')"
  >
    <!--
      Five controls in a 343px row is tight, so the arrows are small, the gaps
      are one step, and the title takes the space that is left and truncates
      rather than pushing anything off the end.
    -->
    <div class="mb-1 flex h-10 items-center gap-1">
      <button
        ref="title"
        class="-ml-1 flex min-w-0 flex-1 items-center gap-1 rounded-xs px-1 py-1 text-left text-control font-medium text-text-strong transition-colors hover:bg-surface-hover focus-visible:focus-ring"
        type="button"
        aria-haspopup="dialog"
        :aria-expanded="jumpOpen"
        :aria-label="t('bookings.calendar.month_label', { month: monthTitle })"
        @click="jumpOpen = !jumpOpen"
      >
        <span class="truncate">{{ monthTitle }}</span>
        <Icon
          name="chevron-down"
          class="h-4 w-4 shrink-0 text-text-subtle transition-transform"
          :class="jumpOpen ? 'rotate-180' : ''"
        />
      </button>

      <!--
        Today and the mode button are the same control with different words, so
        the classes are one string rather than two that can drift apart. The
        mode button names the view you would get, not the one you are in, so it
        reads as somewhere to go.
      -->
      <button
        :class="quietActionClasses"
        type="button"
        :aria-label="t('bookings.calendar.today_label')"
        @click="goToToday"
      >
        {{ t('bookings.calendar.today') }}
      </button>
      <button
        :class="quietActionClasses"
        type="button"
        :aria-label="t(mode === 'week' ? 'bookings.calendar.month_view_label' : 'bookings.calendar.week_view_label')"
        @click="toggleMode"
      >
        {{ t(mode === 'week' ? 'bookings.calendar.month_view' : 'bookings.calendar.week_view') }}
      </button>

      <button
        :class="stepClasses"
        type="button"
        :aria-label="t(mode === 'week' ? 'bookings.calendar.previous_week' : 'bookings.calendar.previous_month')"
        @click="step(-1)"
      >
        <Icon
          name="chevron-left"
          class="h-4 w-4"
        />
      </button>
      <button
        :class="stepClasses"
        type="button"
        :aria-label="t(mode === 'week' ? 'bookings.calendar.next_week' : 'bookings.calendar.next_month')"
        @click="step(1)"
      >
        <Icon
          name="chevron-right"
          class="h-4 w-4"
        />
      </button>
    </div>

    <!--
      A scroll-snapping track of three months, so a drag sideways gets the
      platform's own momentum rather than a hand-rolled one, which matters
      inside a web view. Only the middle panel is ever looked at: when a scroll
      settles on a neighbour the month moves and the track is recentred with no
      animation, so the next drag starts from the middle again.
    -->
    <div
      ref="track"
      class="cal__track"
      @scroll.passive="onTrackScroll"
    >
      <div
        v-for="offset in [-1, 0, 1]"
        :key="offset"
        class="cal__panel"
        :class="offset === 0 ? '' : 'cal__panel--side'"
        :aria-hidden="offset === 0 ? undefined : 'true'"
        :inert="offset === 0 ? undefined : true"
      >
        <MonthGrid
          :month="monthFor(offset)"
          :anchor="anchorFor(offset)"
          :mode="mode"
          :marks="marks"
          :selected="selected"
          :density="density"
          :label="gridLabel(offset)"
          @select="(date) => emit('select', date)"
        />
      </div>
    </div>

    <MonthJumpSheet
      v-model:open="jumpOpen"
      :month="month"
      :months-with-work="monthsWithWork"
      :anchor-to="title"
      @select="onJump"
    />
  </section>
</template>

<script setup lang="ts">
// The calendar half of the Bookings screen: the header row, the grid, the
// swipe, and the sheet that jumps to another month. It owns none of the data
// and none of the filtering; the month, the mode and the selected day all come
// from the screen above it, because the list needs the same three.
import { computed, nextTick, ref, useTemplateRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { addDays, addMonths, format, startOfMonth } from 'date-fns'
import Icon from '@/components/ui/Icon.vue'
import MonthGrid from '@/components/bookings/MonthGrid.vue'
import MonthJumpSheet from '@/components/bookings/MonthJumpSheet.vue'
import { quietActionClasses } from '@/components/bookings/controls'
import type { GridMark } from '@/lib/dayMarks'
import type { GridMode } from '@/lib/monthGrid'

const props = withDefaults(defineProps<{
  month: Date
  marks: ReadonlyMap<string, GridMark>
  monthsWithWork: ReadonlySet<string>
  selected?: Date | null
  mode?: GridMode
  today?: Date
  density?: 'auto' | 'comfortable' | 'compact'
}>(), {
  mode: 'month',
  density: 'auto',
})

const emit = defineEmits<{
  select: [date: Date]
  'update:month': [month: Date]
  'update:mode': [mode: GridMode]
}>()

const { t } = useI18n()

const stepClasses = 'grid h-7 w-7 shrink-0 place-items-center rounded-control text-text-muted transition-colors hover:bg-surface-hover hover:text-text-strong focus-visible:focus-ring'

const today = computed(() => props.today ?? new Date())

const monthTitle = computed(() => format(props.month, t('bookings.calendar.format.month_year')))

const jumpOpen = ref(false)
const title = useTemplateRef<HTMLElement>('title')

// In week mode the strip is built around the selected day, or today when it is
// the month on screen, so that turning to a month with nothing selected lands
// somewhere sensible rather than on the 1st.
const anchor = computed(() => props.selected ?? props.month)

function monthFor(offset: number): Date {
  return props.mode === 'week' ? props.month : addMonths(props.month, offset)
}

function anchorFor(offset: number): Date | null {
  return props.mode === 'week' ? addDays(anchor.value, offset * 7) : null
}

function gridLabel(offset: number): string {
  return format(monthFor(offset), t('bookings.calendar.format.month_year'))
}

function step(direction: number): void {
  if (props.mode === 'week') {
    // A week strip moves the selection, because the selection is what it is
    // built around. Moving the month alone would leave the strip where it was.
    emit('select', addDays(anchor.value, direction * 7))

    return
  }

  emit('update:month', addMonths(props.month, direction))
}

function goToToday(): void {
  emit('update:month', startOfMonth(today.value))

  if (props.mode === 'week') {
    emit('select', today.value)
  }
}

function toggleMode(): void {
  emit('update:mode', props.mode === 'week' ? 'month' : 'week')
}

function onJump(month: Date): void {
  emit('update:month', month)
}

// The swipe track ------------------------------------------------------------
const track = useTemplateRef<HTMLElement>('track')

// Set while the track is being put back to the middle, so the programmatic
// scroll that does it is not mistaken for a swipe.
let recentring = false
let settleTimer: number | null = null

function centre(smooth = false): void {
  const element = track.value

  if (!element) {
    return
  }

  recentring = true
  element.scrollTo({ left: element.clientWidth, behavior: smooth ? 'smooth' : 'auto' })

  // scrollTo fires no event when the position does not change, so the flag is
  // cleared on a timer rather than on a scrollend that may never arrive.
  window.setTimeout(() => {
    recentring = false
  }, 0)
}

function onTrackScroll(): void {
  const element = track.value

  if (!element || recentring) {
    return
  }

  if (settleTimer !== null) {
    window.clearTimeout(settleTimer)
  }

  // Wait for the scroll to settle rather than acting on every frame of it,
  // or a drag past the halfway point turns two months instead of one.
  settleTimer = window.setTimeout(() => {
    const panel = element.clientWidth

    if (panel === 0) {
      return
    }

    const landed = Math.round(element.scrollLeft / panel) - 1

    if (landed !== 0) {
      step(landed)
    }
  }, 120)
}

// Recentre whenever the panels are rebuilt, so the next drag starts from the
// middle. Without smoothing, because the content under the finger has just
// been replaced and animating it back would look like a second swipe.
watch([() => props.month, () => props.mode, anchor], async () => {
  await nextTick()
  centre()
}, { immediate: true, flush: 'post' })
</script>

<style scoped>
/*
  Three months wide, one month visible, snapping to whichever is nearest. The
  scrollbar is hidden because the track is a gesture surface rather than
  something to scroll deliberately.
*/
.cal__track {
  display: grid;
  grid-auto-flow: column;
  grid-auto-columns: 100%;
  overflow-x: auto;
  /* The neighbours are clipped rather than allowed to stretch the track. See
     below for why they cannot be allowed to set its height. */
  overflow-y: hidden;
  overscroll-behavior-x: contain;
  scroll-snap-type: x mandatory;
  scrollbar-width: none;
}

.cal__track::-webkit-scrollbar {
  display: none;
}

.cal__panel {
  position: relative;
  scroll-snap-align: center;
  /* A grid column will not shrink below its content without this, which on a
     narrow phone lets the track scroll horizontally by a few pixels at rest. */
  min-width: 0;
}

/*
  The month either side is drawn so a drag has something to move onto, but it
  must not decide how tall the track is. Months are four, five or six rows
  deep, so a five-row September sitting next to a six-row August would leave a
  row's worth of empty space under the grid and push the list down for no
  reason anybody could see.

  Taking the neighbours out of flow makes the centre panel the only one with a
  height, which is the right answer: the track is as tall as the month you are
  looking at.
*/
.cal__panel--side > * {
  position: absolute;
  inset-inline: 0;
  top: 0;
}

@media (prefers-reduced-motion: reduce) {
  .cal__track {
    scroll-behavior: auto;
  }
}
</style>
