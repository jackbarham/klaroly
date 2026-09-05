<template>
  <div class="cal-grid">
    <!--
      The headings carry the same 1px gap and the same 1px of side padding as
      the body below, so each label sits over its own column rather than
      drifting half a gap out of line.
    -->
    <div
      class="cal-grid__head"
      aria-hidden="true"
    >
      <span
        v-for="heading in headings"
        :key="heading"
        class="text-caption text-text-subtle"
      >{{ heading }}</span>
    </div>

    <!--
      role="grid" with a real row element per week, rather than display:
      contents on a wrapper, because a row that has no box is a row some
      browsers have historically dropped out of the accessibility tree. Each
      week is its own seven-column grid and the 1px gaps let the container's
      colour through, which is how the rules between days are drawn: no cell
      carries a border, so no two cells double up and the outer edge is the
      same weight as the inside.
    -->
    <div
      ref="body"
      class="cal-grid__body"
      role="grid"
      :aria-label="label"
      @keydown="onKeydown"
    >
      <div
        v-for="(week, index) in weeks"
        :key="index"
        class="cal-grid__week"
        role="row"
      >
        <button
          v-for="day in week"
          :key="day.key"
          class="cal-grid__day focus-visible:focus-ring focus-visible:-outline-offset-2"
          :class="[densityClasses, dayClasses(day)]"
          type="button"
          role="gridcell"
          :data-key="day.key"
          :aria-label="dayLabel(day)"
          :aria-selected="isSelected(day)"
          :tabindex="day.key === rovingKey ? 0 : -1"
          @click="emit('select', day.date)"
        >
          <span class="cal-grid__slot">
            <span class="cal-grid__mark">{{ day.dayOfMonth }}</span>
            <span
              v-if="marks.get(day.key)?.possible"
              class="cal-grid__badge"
            >{{ marks.get(day.key)?.possible }}</span>
          </span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// The month grid, and nothing but the grid. It takes a month, a map of marks
// keyed 'YYYY-MM-DD', a selected day and a density, and it emits a date when
// one is tapped. It has never heard of a booking, which is what would let the
// same component draw availability or blocked-out days without a rewrite: a
// caller with a different idea of what a day means builds a different map.
import { computed, ref, useTemplateRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { format } from 'date-fns'
import { dayKey, useMonthGrid, weekdayHeadings, type GridDay, type GridMode } from '@/lib/monthGrid'
import type { GridMark } from '@/lib/dayMarks'

const props = withDefaults(defineProps<{
  month: Date
  marks: ReadonlyMap<string, GridMark>
  label: string
  selected?: Date | null
  today?: Date
  mode?: GridMode
  // In week mode, the day the strip is built around.
  anchor?: Date | null
  // 'auto' lets the container query decide, which is what the screen wants.
  // The other two pin it, so the kitchen sink can show both shapes side by
  // side at one width. It is not a required prop: a required one that is
  // nearly always 'auto' is a prop everyone gets wrong once.
  density?: 'auto' | 'comfortable' | 'compact'
}>(), {
  mode: 'month',
  density: 'auto',
})

const emit = defineEmits<{
  select: [date: Date]
}>()

const { t } = useI18n()

const { days, weeks } = useMonthGrid(() => props.month, () => props.mode, () => props.anchor ?? null)

const headings = computed(() => weekdayHeadings(t('bookings.calendar.format.weekday_heading')))

// The cell's shape, and the one thing on this component that has to be a
// Tailwind variant rather than a rule in the style block below: a CSS
// container query cannot read a var(), so @container (min-width: 760px)
// written by hand would be the 760 hardcoded. The @split variant is generated
// from --container-split, so the token is still the only place the number
// lives.
//
// A phone cell is square and never smaller than the tap minimum. A wide one
// drops the square, because a column of 60px-tall cells beside a list reads
// better than a grid of tall boxes.
const densityClasses = computed(() => {
  const phone = 'aspect-square min-h-(--tap-target-min)'
  const wide = 'aspect-auto min-h-16'

  if (props.density === 'comfortable') {
    return phone
  }

  if (props.density === 'compact') {
    return wide
  }

  return `${phone} @split:aspect-auto @split:min-h-16`
})

const todayKey = computed(() => dayKey(props.today ?? new Date()))

const selectedKey = computed(() => (props.selected ? dayKey(props.selected) : null))

function isSelected(day: GridDay): boolean {
  return day.key === selectedKey.value
}

function dayClasses(day: GridDay): string[] {
  const mark = props.marks.get(day.key)
  const classes: string[] = []

  if (!day.inMonth) {
    classes.push('is-outside')
  }

  if (day.key === todayKey.value) {
    classes.push('is-today')
  }

  if (isSelected(day)) {
    classes.push('is-selected')
  }

  if (mark) {
    classes.push(`is-${mark.strength}`)
  }

  return classes
}

// The whole day read out, because a filled circle and a small orange number
// say nothing to a screen reader: "Saturday 14 June, 1 confirmed booking,
// 3 enquiries", or "nothing on".
function dayLabel(day: GridDay): string {
  const mark = props.marks.get(day.key)
  const parts: string[] = []

  if (mark) {
    if (mark.confirmed > 0) {
      parts.push(t('bookings.calendar.confirmed_count', { count: mark.confirmed }, mark.confirmed))
    }

    if (mark.provisional > 0) {
      parts.push(t('bookings.calendar.provisional_count', { count: mark.provisional }, mark.provisional))
    }

    if (mark.possible > 0) {
      parts.push(t('bookings.calendar.enquiry_count', { count: mark.possible }, mark.possible))
    }
  }

  return t('bookings.calendar.day_label', {
    day: format(day.date, t('bookings.calendar.format.day_full')),
    summary: parts.length > 0 ? parts.join(', ') : t('bookings.calendar.day_nothing'),
  })
}

// Roving tabindex: exactly one cell is in the tab order, and the arrow keys
// move which. Without it a keyboard user tabs through forty-two buttons to
// get past a calendar.
const body = useTemplateRef<HTMLElement>('body')
const rovingKey = ref<string | null>(null)

// Where the tab stop sits when nobody has moved it: the selected day, else
// today if this month contains it, else the first day of the month.
const defaultKey = computed(() => {
  if (selectedKey.value && days.value.some((day) => day.key === selectedKey.value)) {
    return selectedKey.value
  }

  if (days.value.some((day) => day.key === todayKey.value)) {
    return todayKey.value
  }

  return days.value.find((day) => day.inMonth)?.key ?? days.value[0]?.key ?? null
})

watch([defaultKey, days], () => {
  // A month change rebuilds every key, so a roving position from the old
  // month would leave no cell in the tab order at all.
  if (rovingKey.value === null || !days.value.some((day) => day.key === rovingKey.value)) {
    rovingKey.value = defaultKey.value
  }
}, { immediate: true })

const steps: Record<string, number> = {
  ArrowRight: 1,
  ArrowLeft: -1,
  ArrowDown: 7,
  ArrowUp: -7,
}

function onKeydown(event: KeyboardEvent): void {
  const current = days.value.findIndex((day) => day.key === rovingKey.value)

  if (current === -1) {
    return
  }

  let next: number

  if (event.key in steps) {
    next = current + steps[event.key]
  } else if (event.key === 'Home') {
    // The start of this week, which is the row the focus is on.
    next = current - (current % 7)
  } else if (event.key === 'End') {
    next = current - (current % 7) + 6
  } else {
    return
  }

  // Focus stays inside the days on screen. Running off the end does nothing
  // rather than silently turning the month under the person's hands.
  if (next < 0 || next >= days.value.length) {
    event.preventDefault()

    return
  }

  event.preventDefault()
  rovingKey.value = days.value[next].key
  body.value?.querySelector<HTMLElement>(`[data-key="${days.value[next].key}"]`)?.focus()
}
</script>

<style scoped>
/*
  Hand-written rather than utilities for one reason: the rules between the days
  are 1px gaps over a coloured container, and the cells, the gaps and the
  heading row all have to agree about that 1px. Written as Tailwind variants it
  is the same arithmetic spread over four class strings.

  Every value below is a token.
*/
.cal-grid__head {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 1px;
  /* The body has a 1px border, so the headings need the same inset or each
     label sits a pixel left of its column. */
  padding: 0 1px calc(var(--spacing) * 1);
  text-align: center;
}

.cal-grid__body {
  display: flex;
  flex-direction: column;
  gap: 1px;
  background: var(--cal-line);
  border: 1px solid var(--cal-line);
  border-radius: var(--radius-control);
  overflow: hidden;
}

.cal-grid__week {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 1px;
}

/*
  The whole cell is the tap target, which is why the mark inside it is only
  36px: 44pt is the minimum touchable area, not the minimum dot.

  --day-ground is the cell's own background, declared here so the badge's ring
  can match whatever the cell is painted, including today's.
*/
.cal-grid__day {
  --day-ground: var(--surface);

  position: relative;
  display: grid;
  place-items: center;
  padding: 0;
  background: var(--day-ground);
  color: var(--text);
  -webkit-tap-highlight-color: transparent;
}

.cal-grid__day.is-today {
  --day-ground: var(--surface-hover);
}

/* The selected day is a ring around the cell, drawn inside so it does not sit
   over the rules on either side of it. */
.cal-grid__day.is-selected {
  box-shadow: inset 0 0 0 var(--border-width-strong) var(--accent);
}

/* Outside-month days fade their contents, never the cell: fading the cell
   greys its background and the day reads as shaded rather than quiet. */
.cal-grid__day.is-outside .cal-grid__slot {
  opacity: 0.4;
}

.cal-grid__slot {
  position: relative;
  display: grid;
  place-items: center;
}

.cal-grid__mark {
  display: grid;
  place-items: center;
  /* 36px, the small control height. A mark is a small control's worth of
     circle sitting inside a 44px minimum cell. */
  width: var(--control-height-sm);
  height: var(--control-height-sm);
  border-radius: var(--radius-pill);
  font-variant-numeric: tabular-nums;
  transition: background-color var(--duration-fast) var(--ease-out);
}

/*
  The three marks. They differ by shape first and colour second: filled,
  outlined, and a badge that can appear alongside either. Roughly one man in
  twelve cannot separate these by hue, and a grid that says nothing to them is
  a grid that says nothing.
*/
.cal-grid__day.is-confirmed .cal-grid__mark {
  background: var(--accent);
  color: var(--text-on-accent);
  font-weight: 500;
}

.cal-grid__day.is-provisional .cal-grid__mark {
  box-shadow: inset 0 0 0 var(--border-width-strong) var(--accent);
  color: var(--text-strong);
  font-weight: 500;
}

/*
  The enquiry count, which sits on the edge of the circle so it can appear
  beside a filled or an outlined mark rather than instead of one. That is the
  case the screen exists for: one confirmed booking and three live enquiries on
  the same Saturday, both visible at once.
*/
.cal-grid__badge {
  position: absolute;
  top: calc(var(--spacing) * -1);
  right: calc(var(--spacing) * -1.5);
  display: grid;
  place-items: center;
  min-width: calc(var(--spacing) * 4);
  height: calc(var(--spacing) * 4);
  padding: 0 calc(var(--spacing) * 1);
  border-radius: var(--radius-pill);
  background: var(--warning-solid);
  color: var(--text-on-accent);
  font-size: var(--text-caption);
  line-height: 1;
  font-weight: 500;
  font-variant-numeric: tabular-nums;
  /* Cut out of whatever the cell is painted, so the badge reads as sitting on
     top of the circle rather than merging with it. */
  box-shadow: 0 0 0 2px var(--day-ground);
}

/* The cell's own shape is set in the class attribute, by densityClasses, and
   deliberately not here: a rule in this block carries the scope attribute's
   specificity and would beat the utility it is meant to defer to. */
</style>
