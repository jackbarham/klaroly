<template>
  <div>
    <!--
      Filtered to a day, the header is that day with a way out. Unfiltered
      there is no header at all: the group headings below are the structure,
      and a second sticky bar above them would be one too many.
    -->
    <div
      v-if="selected"
      class="sticky stick-top z-3 flex items-center gap-2 border-y border-border bg-surface-sunken px-4 py-2 @split:px-6"
    >
      <span class="min-w-0 truncate text-caption font-medium tracking-wide text-text-muted uppercase">
        {{ selectedTitle }}
      </span>
      <button
        class="ml-auto shrink-0 rounded-xs text-meta whitespace-nowrap text-accent-text transition-colors hover:text-accent focus-visible:focus-ring"
        type="button"
        @click="emit('clear')"
      >
        {{ t('bookings.list.show_all') }}
      </button>
    </div>

    <EmptyState
      v-if="groups.length === 0"
      icon="calendar"
      :text="selected ? t('bookings.list.empty_day') : t('bookings.list.empty')"
    />

    <!--
      Each band is its own list item wrapping its own list of rows, rather
      than every heading and every row being siblings in one flat ul. Two
      reasons, and the first is the one that shows: a sticky element is
      bounded by its containing block, so in a flat list every heading pins to
      the top of the page and stays there, and This week, This month and Next
      three months end up stacked on top of each other, with only paint order
      deciding which one you see. Bounded by its own band, a heading is pushed
      out by the next one the way a sticky heading should be. The second is
      that a heading is then a heading rather than a list item pretending not
      to be one.
    -->
    <ul
      v-else
      :aria-label="t('bookings.list.label')"
    >
      <li
        v-for="group in groups"
        :key="group.key"
      >
        <h2
          v-if="group.labelKey"
          class="sticky stick-top z-2 border-y border-border bg-surface-sunken px-4 py-2 text-caption font-medium tracking-wide text-text-muted uppercase @split:px-6"
        >
          {{ t(group.labelKey) }}
        </h2>
        <ul>
          <BookingRow
            v-for="event in group.events"
            :key="event.id"
            :event="event"
            :today="today"
          />
        </ul>
      </li>
    </ul>
  </div>
</template>

<script setup lang="ts">
// The list half of the screen. Unfiltered it shows what is coming up, grouped
// under sticky headings with the urgent end at the top. Tapping a day filters
// it to that day, and a day filter shows everything on the day, including work
// that has already happened.
//
// The tabs for Upcoming, Past and All and the status filter that business
// logic 19.2 describes are deliberately not here; they arrive once the shape
// of the list is settled.
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { differenceInCalendarDays, format, parseISO } from 'date-fns'
import BookingRow from '@/components/bookings/BookingRow.vue'
import { dayKey } from '@/lib/monthGrid'
import type { BookingEvent } from '@/types/bookings'

const props = defineProps<{
  events: BookingEvent[]
  selected?: Date | null
  today?: Date
}>()

const emit = defineEmits<{
  clear: []
}>()

const { t } = useI18n()

const today = computed(() => props.today ?? new Date())

const selectedTitle = computed(() => (props.selected
  ? format(props.selected, t('bookings.calendar.format.day_full_year'))
  : ''))

interface Group {
  key: string
  // A day filter has its own header above the list, so its single group is
  // unlabelled rather than carrying a second heading nobody asked for.
  labelKey: string | null
  events: BookingEvent[]
}

// Where a date falls, in the four bands from business logic 19.2. Earlier is
// reachable only through a day filter, because the unfiltered list is upcoming
// only.
function bandFor(date: Date): { key: string, labelKey: string } {
  const days = differenceInCalendarDays(date, today.value)

  if (days < 0) {
    return { key: 'earlier', labelKey: 'bookings.list.earlier' }
  }

  if (days <= 6) {
    return { key: 'this_week', labelKey: 'bookings.list.this_week' }
  }

  if (days <= 31) {
    return { key: 'this_month', labelKey: 'bookings.list.this_month' }
  }

  if (days <= 92) {
    return { key: 'next_three', labelKey: 'bookings.list.next_three_months' }
  }

  return { key: 'later', labelKey: 'bookings.list.later' }
}

const groups = computed<Group[]>(() => {
  const sorted = [...props.events].sort((a, b) => a.date.localeCompare(b.date))

  if (props.selected) {
    const key = dayKey(props.selected)
    const onDay = sorted.filter((event) => event.date === key)

    return onDay.length > 0 ? [{ key, labelKey: null, events: onDay }] : []
  }

  const todayKey = dayKey(today.value)
  const upcoming = sorted.filter((event) => event.date >= todayKey)
  const bands: Group[] = []

  for (const event of upcoming) {
    const band = bandFor(parseISO(event.date))
    const last = bands[bands.length - 1]

    if (last && last.key === band.key) {
      last.events.push(event)

      continue
    }

    bands.push({ key: band.key, labelKey: band.labelKey, events: [event] })
  }

  return bands
})
</script>
