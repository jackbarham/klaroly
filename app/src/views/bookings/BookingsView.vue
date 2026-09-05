<template>
  <div class="@container">
    <PageHeader :title="t('bookings.title')">
      <template #actions>
        <!--
          A disclosure, not a pressed toggle: the word names what tapping does
          and changes with the state, and a label that describes the action
          contradicts aria-pressed, which describes the state.
        -->
        <button
          :class="[quietActionClasses, 'flex items-center gap-1.5']"
          type="button"
          :aria-expanded="calendarVisible"
          :aria-controls="calendarId"
          @click="toggleCalendar"
        >
          {{ t(calendarVisible ? 'bookings.hide_calendar' : 'bookings.show_calendar') }}
          <Icon
            name="calendar"
            class="h-5 w-5"
          />
        </button>
      </template>
    </PageHeader>

    <p
      v-if="bookings.status === 'failed'"
      class="rounded-card border border-border bg-surface-raised p-4 text-sm text-text"
      role="status"
    >
      {{ t('bookings.failed') }}
    </p>

    <!--
      Below the breakpoint the calendar is a band above the list; above it, a
      column beside it. One document scrolls at both sizes and the calendar is
      sticky at both, so there is one scroll container, one sync handler and
      nothing that needs a height worked out from the viewport.
    -->
    <div class="@split:flex @split:items-start @split:gap-6">
      <div
        :id="calendarId"
        ref="calendarWrap"
        class="calwrap sticky stick-top z-10 -mx-6 bg-surface px-6 @split:mx-0 @split:shrink-0 @split:px-0"
        :class="calendarVisible ? '@split:w-100' : 'is-collapsed @split:w-0'"
      >
        <!--
          The padding sits on this inner element rather than on the wrapper,
          because min-height: 0 closes a content box and leaves its padding
          behind, and a collapsed calendar that keeps eight pixels of gap is a
          sliver nobody can explain.
        -->
        <!--
          Capped at the width the calendar is drawn for, which is the same 400
          it gets as a column. Without it a stacked calendar takes whatever the
          container gives it, and at 1024px, where the sidebar has appeared but
          <main> is still under the split, that is a 688px band of 97px cells
          with the list shoved off the bottom of the screen. The wrapper stays
          full width so the sticky background still covers the rows passing
          under it.
        -->
        <div class="max-w-100 pb-2">
          <BookingsCalendar
            v-model:month="month"
            v-model:mode="mode"
            :marks="marks"
            :months-with-work="monthsWithWork"
            :selected="selected"
            :today="today"
            @select="onSelectDay"
          />
        </div>
      </div>

      <div
        ref="listWrap"
        class="min-w-0 grow -mx-6 @split:mx-0"
        :style="{ '--stick-offset': `${stickOffset}px` }"
      >
        <BookingList
          :events="bookings.events"
          :selected="selected"
          :today="today"
          @clear="clearFilter"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// Bookings: a month calendar and a list, two views of the same data on one
// screen. This component owns the three things both halves need, the month on
// show, the selected day and the calendar's mode, and the one thing neither
// can own, the sync between them.
import { nextTick, onBeforeUnmount, onMounted, ref, useId, useTemplateRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { isSameMonth, parseISO, startOfMonth } from 'date-fns'
import Icon from '@/components/ui/Icon.vue'
import BookingList from '@/components/bookings/BookingList.vue'
import BookingsCalendar from '@/components/bookings/BookingsCalendar.vue'
import { quietActionClasses } from '@/components/bookings/controls'
import { useDayMarks } from '@/lib/dayMarks'
import { dayKey } from '@/lib/monthGrid'
import { useBookingsStore } from '@/stores/bookings'
import type { GridMode } from '@/lib/monthGrid'

const { t } = useI18n()
const bookings = useBookingsStore()

// Read once, here, rather than calling new Date() in six places, so that every
// part of the screen agrees about what today is even across midnight.
const today = ref(new Date())

const month = ref(startOfMonth(today.value))
const selected = ref<Date | null>(null)
const mode = ref<GridMode>('month')
const calendarVisible = ref(true)
const calendarId = useId()

const { marks, monthsWithWork } = useDayMarks(() => bookings.events)

const calendarWrap = useTemplateRef<HTMLElement>('calendarWrap')
const listWrap = useTemplateRef<HTMLElement>('listWrap')

onMounted(() => {
  bookings.load()
})

// Where the list's group headings come to rest ------------------------------
//
// Both the calendar and the headings are sticky, so below the breakpoint,
// where the calendar is a band across the top, a heading pinned to the same
// line disappears behind it and the list has no visible headings at all.
// Above the breakpoint the calendar is a column beside the list and the
// headings should sit level with it, offset by nothing.
//
// Which of those it is cannot be asked of the container query from here, so it
// is measured instead: if the calendar's right edge is left of the list, they
// are side by side.
//
// **Do not tidy this into a width check against --container-split.** That is
// the obvious-looking simplification and it is the whole thing this avoids: it
// would put a second copy of the breakpoint in JavaScript, where nothing keeps
// it in step with the CSS that actually decides the layout, and the two would
// drift the first time the split moves or the calendar's column changes width.
// Asking the elements where they are cannot drift, and it stays correct if the
// layout is ever given a third shape.
const stickOffset = ref(0)

function measureStickOffset(): void {
  const calendar = calendarWrap.value
  const list = listWrap.value

  if (!calendar || !list) {
    return
  }

  const calendarBox = calendar.getBoundingClientRect()
  const sideBySide = calendarBox.right <= list.getBoundingClientRect().left + 1

  stickOffset.value = sideBySide ? 0 : Math.round(calendarBox.height)
}

// A ResizeObserver rather than a watcher, because the calendar's height moves
// for reasons this component does not always initiate: a month with more rows,
// the collapse animating, the week strip, and the window being resized. It
// fires throughout an animation, so the headings follow the calendar down
// rather than jumping when it lands.
let sizes: ResizeObserver | null = null

onMounted(() => {
  measureStickOffset()

  sizes = new ResizeObserver(measureStickOffset)

  if (calendarWrap.value) {
    sizes.observe(calendarWrap.value)
  }

  if (listWrap.value) {
    sizes.observe(listWrap.value)
  }
})

onBeforeUnmount(() => {
  sizes?.disconnect()
  sizes = null
})

// Tapping a day filters the list to it, and tapping the same day again clears
// the filter. One gesture, one meaning.
function onSelectDay(date: Date): void {
  holdSync()

  const key = dayKey(date)

  selected.value = selected.value && dayKey(selected.value) === key ? null : date

  // Tapping a day in the padding either side of the month moves to it, so the
  // day just tapped is not left sitting outside the month on screen.
  if (mode.value === 'month' && !isSameMonth(date, month.value)) {
    month.value = startOfMonth(date)
  }
}

function clearFilter(): void {
  selected.value = null

  // The list is back to its full length, so it goes back to the top and the
  // calendar catches up with what is now on screen. Clearing is deliberate, so
  // it overrides the hold a moment of tapping put on the sync.
  window.scrollTo({ top: 0 })
  syncHeldUntil = 0
  requestAnimationFrame(syncFromScroll)
}

function toggleCalendar(): void {
  calendarVisible.value = !calendarVisible.value
}

// The height change between a four, five and six row month -----------------
//
// Moving between months changes the calendar's height, which shunts the list
// under it. Animate that rather than letting it snap: measure before, apply,
// measure after, put the old height back, force a reflow so the browser has
// something to animate from, then set the new one and clear it when it lands.
//
// It is a watcher rather than something each caller wraps its change in,
// because there are five ways to change the month, the arrows, the swipe, a
// day in the padding, the jump sheet and Today, and the first version of this
// only animated the one that happened to go through the tap handler. A watcher
// cannot be forgotten by the sixth.
let heightTimer: number | null = null

watch([month, mode], () => {
  const element = calendarWrap.value

  if (!element || !calendarVisible.value) {
    return
  }

  // Pre-flush, so this reads the height the calendar still has.
  const from = element.getBoundingClientRect().height

  nextTick(() => {
    element.style.height = ''

    const to = element.getBoundingClientRect().height

    if (Math.abs(to - from) < 1) {
      return
    }

    element.style.height = `${from}px`
    // Reading a layout property is what commits the starting height, so the
    // transition has two values to move between rather than one.
    void element.offsetHeight
    element.style.height = `${to}px`

    if (heightTimer !== null) {
      window.clearTimeout(heightTimer)
    }

    // Cleared rather than left inline, or the next month would animate from a
    // height that is no longer true.
    heightTimer = window.setTimeout(() => {
      element.style.height = ''
    }, 400)
  })
}, { flush: 'pre' })

// Following the list --------------------------------------------------------
//
// One direction only: scrolling the list moves the calendar, and nothing the
// calendar does ever scrolls the list. Two-way sync is where the loops live,
// and this screen already has a second feedback path in the row count changing
// the calendar's height.
let syncHeldUntil = 0

function holdSync(): void {
  syncHeldUntil = Date.now() + 450
}

function syncFromScroll(): void {
  // A day filter is one day, so there is nothing to follow. A week strip
  // cannot meaningfully follow a list spanning three months, and trying would
  // flick it on every scroll, so the sync is a month-view behaviour.
  if (selected.value || !calendarVisible.value || mode.value === 'week') {
    return
  }

  if (Date.now() < syncHeldUntil) {
    return
  }

  const element = calendarWrap.value

  if (!element) {
    return
  }

  // Anything above the bottom of the calendar is behind it, so the first row
  // clear of that edge is the one the artist is actually looking at.
  const edge = element.getBoundingClientRect().bottom

  for (const row of document.querySelectorAll<HTMLElement>('[data-date]')) {
    if (row.getBoundingClientRect().bottom <= edge) {
      continue
    }

    const date = parseISO(row.dataset.date ?? '')

    if (!isSameMonth(date, month.value)) {
      // Only the month changes, which redraws the grid. The list's props do
      // not depend on it, so Vue leaves the list alone and the scroll position
      // this handler is reading from survives.
      month.value = startOfMonth(date)
    }

    return
  }
}

let queued = false

function onScroll(): void {
  if (queued) {
    return
  }

  queued = true
  requestAnimationFrame(() => {
    queued = false
    syncFromScroll()
  })
}

onMounted(() => {
  window.addEventListener('scroll', onScroll, { passive: true })
})

onBeforeUnmount(() => {
  window.removeEventListener('scroll', onScroll)

  if (heightTimer !== null) {
    window.clearTimeout(heightTimer)
  }
})

// Anything the calendar drives holds the sync off, so the two cannot chase
// each other while a month change settles.
watch([month, mode], holdSync)
</script>

<style scoped>
/*
  Hiding the calendar animates a grid track from 1fr to 0fr, not a height:
  a month is four, five or six rows deep, so any fixed height is wrong for most
  months and 0fr resolves to zero whatever is in it.

  Above the breakpoint the calendar is a column rather than a band, so it also
  hands its width to the list. The width is a utility in the template, because
  a container query cannot read --container-split; both axes run on the same
  class and the same duration, and the axis follows the layout.
*/
.calwrap {
  display: grid;
  grid-template-rows: 1fr;
  overflow: hidden;
  transition:
    grid-template-rows var(--duration-base) var(--ease-out),
    width var(--duration-base) var(--ease-out),
    height var(--duration-base) var(--ease-out);
}

.calwrap.is-collapsed {
  grid-template-rows: 0fr;
}

/* min-height: 0 lets the track close, but it zeroes a content box and not its
   padding, so the padding has to fold away too or an 8px sliver is left
   behind. */
.calwrap > * {
  min-height: 0;
  overflow: hidden;
  transition:
    opacity var(--duration-base) var(--ease-out),
    padding var(--duration-base) var(--ease-out);
}

.calwrap.is-collapsed > * {
  opacity: 0;
  padding-block: 0;
}

@media (prefers-reduced-motion: reduce) {
  .calwrap,
  .calwrap > * {
    transition: none;
  }
}
</style>
