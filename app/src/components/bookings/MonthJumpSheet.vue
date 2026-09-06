<template>
  <AnchoredSheet
    v-model:open="open"
    :label="t('bookings.calendar.jump_label')"
    :anchor-to="anchorTo"
    align="left"
    width-class="lg:w-80"
  >
    <!--
      The year strip. Its own scrollLeft is set to centre the current year,
      never scrollIntoView, which walks every scrollable ancestor including the
      document and would drag the page down behind the sheet.
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
          A dot on every month that has something in it. An artist's diary is
          mostly empty with clusters on summer Saturdays, so "where is my work"
          is a more common question than "take me to March", and that is the
          part a stock date picker cannot answer.
        -->
        <span
          v-if="hasWork(index)"
          class="absolute bottom-1.5 left-1/2 h-1 w-1 -translate-x-1/2 rounded-full"
          :class="isShown(index) ? 'bg-text-on-accent' : 'bg-accent'"
          aria-hidden="true"
        />
      </button>
    </div>
  </AnchoredSheet>
</template>

<script setup lang="ts">
// Two taps to any month in any year: pick a year from the strip, then a month
// from the grid.
//
// The panel around it is ui/AnchoredSheet.vue, which the contacts view menu
// also uses: a bottom sheet below lg and a panel hanging under a measured
// button at lg. It is left-aligned here because the month title sits near the
// left of the calendar, so the panel grows rightwards away from it. Everything
// in this file is about the content: which years the strip offers, which month
// is shown, and where the strip is scrolled to when it opens.
import { computed, nextTick, ref, useTemplateRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { format, startOfMonth } from 'date-fns'

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

const strip = useTemplateRef<HTMLElement>('strip')

// Formatted rather than listed, so these are the same month names the header
// and the rows use and there is no second spelling of September in the app.
const monthNames = computed(() => {
  const pattern = t('bookings.calendar.format.month_short')

  return Array.from({ length: 12 }, (unused, index) => format(new Date(2000, index, 1), pattern))
})

// The years the strip offers, taken from the months the account actually has
// work in and extended a year each way.
//
// It used to be the current year minus one to plus four, carried over from the
// prototype, which meant an artist with a 2031 wedding had a dot she could not
// navigate to: the range and the dots came from two places and disagreed. Both
// now come from the summary, so they cannot.
//
// With nothing in the diary at all there is nothing to derive from, so the
// fallback is a year either side of this one, which is where somebody starting
// out would be looking.
const years = computed(() => {
  const current = new Date().getFullYear()
  const inUse = [...props.monthsWithWork].map((month) => Number(month.slice(0, 4)))

  const first = inUse.length > 0 ? Math.min(...inUse, current) - 1 : current - 1
  const last = inUse.length > 0 ? Math.max(...inUse, current) + 1 : current + 1

  return Array.from({ length: last - first + 1 }, (unused, index) => first + index)
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

// Opening starts on the month the calendar is showing, and the strip is
// scrolled so that year is in the middle rather than off the left edge.
watch(open, async (isOpen) => {
  if (!isOpen) {
    return
  }

  shownYear.value = props.month.getFullYear()

  await nextTick()

  const active = strip.value?.querySelector<HTMLElement>('[aria-pressed="true"]')

  if (strip.value && active) {
    strip.value.scrollLeft = active.offsetLeft - (strip.value.clientWidth - active.offsetWidth) / 2
  }
})
</script>

<style scoped>
.jump__years {
  scrollbar-width: none;
}

.jump__years::-webkit-scrollbar {
  display: none;
}
</style>
