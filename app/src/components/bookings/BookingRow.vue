<template>
  <!--
    The date is on the element so the screen's scroll sync can read which
    month is at the top of the list without the list telling it.
  -->
  <li :data-date="event.date">
    <RouterLink
      class="booking-row flex w-full items-start gap-3 border-b border-border px-4 py-3 text-left transition-colors hover:bg-row-hover focus-visible:focus-ring focus-visible:-outline-offset-2 @split:px-6"
      :to="{ name: 'booking', params: { id: event.bookingId } }"
    >
      <!--
        A spine rather than a coloured row: the state is already in words on
        the pill below, and this is the thing that lets the eye run down the
        list and find the confirmed work without reading any of it.
      -->
      <span
        class="mt-0.5 w-1 shrink-0 self-stretch rounded-full"
        :class="spineClasses"
        aria-hidden="true"
      />

      <span class="min-w-0 grow">
        <span class="flex items-baseline gap-2">
          <span class="booking-row__name truncate text-control font-medium text-text-strong transition-colors">
            {{ event.clientName }}
          </span>
          <span
            v-if="event.totalMinor > 0"
            class="ml-auto shrink-0 text-body font-medium tabular-nums text-text-strong"
          >{{ total }}</span>
        </span>

        <span class="block truncate text-meta text-text-muted">{{ meta }}</span>

        <span class="mt-1.5 flex flex-wrap items-center gap-1.5">
          <StatusPill :tone="stageTone">{{ t(`bookings.stage.${event.stage}`) }}</StatusPill>
          <StatusPill
            v-if="event.type !== 'main'"
            tone="neutral"
          >{{ typeName }}</StatusPill>
          <StatusPill
            v-if="event.waitingOn"
            tone="warning"
          >{{ t(`bookings.waiting.${event.waitingOn}`) }}</StatusPill>
          <!--
            How stale, as quiet meta rather than a third pill. The waiting-on
            pill is the authority on whether an enquiry has gone cold; this is
            the fact that decides whether a clash is worth a phone call, and
            only one of the two should look like a status.
          -->
          <span
            v-if="showTouched"
            class="text-caption text-text-subtle"
          >{{ touched }}</span>
        </span>
      </span>
    </RouterLink>
  </li>
</template>

<script setup lang="ts">
// One event in the list: who, when, where, how much, and what it is waiting
// on. It is an event rather than a booking, so a client with a trial in March
// and a wedding in June appears twice, which is what the artist wants when she
// is looking at a diary.
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { differenceInCalendarDays, format, parseISO } from 'date-fns'
import type { PillTone } from '@/components/ui/StatusPill.vue'
import type { BookingEvent, BookingStage } from '@/types/bookings'

const props = defineProps<{
  event: BookingEvent
  today?: Date
}>()

const { t, n } = useI18n()

// Which stage reads as which tone is this screen's decision; StatusPill knows
// nothing about bookings. Confirmed and completed are success because the
// style guide gives --color-success to "confirmed, paid". Everything still in
// play is neutral, because an enquiry at New is not good news or bad news, it
// is simply where it is. Lost and cancelled are the only two that failed.
const toneByStage: Record<BookingStage, PillTone> = {
  new: 'neutral',
  in_conversation: 'neutral',
  possible: 'warning',
  quoted: 'warning',
  provisional: 'neutral',
  confirmed: 'success',
  completed: 'success',
  closed: 'neutral',
  lost: 'danger',
  cancelled: 'danger',
}

const stageTone = computed(() => toneByStage[props.event.stage])

// events.label is a custom display name; without one the type's own name is
// used. A main event needs neither, because the row is about a wedding by
// default and saying so on every row is noise.
const typeName = computed(() => props.event.label ?? t(`bookings.event_type.${props.event.type}`))

// Money never becomes a string by putting a pound sign in front of a number.
// The minor units are divided here rather than stored as a decimal: the
// integer is the value, and this is the last possible moment before it is
// drawn.
const total = computed(() => n(props.event.totalMinor / 100, {
  key: 'currency',
  currency: props.event.currency,
}))

// The date, the time when there is one, and the venue. venue_name is only set
// when the occasion's venue differs from the address the work happens at, so
// the fall back to the city is the common case rather than the exception, and
// an enquiry with neither says so in words rather than leaving a gap.
const meta = computed(() => {
  const parts = [format(parseISO(props.event.date), t('bookings.calendar.format.row_date'))]

  if (props.event.startTime) {
    parts.push(props.event.startTime)
  }

  parts.push(props.event.venueName ?? props.event.city ?? t('bookings.list.no_venue'))

  return parts.join(' · ')
})

// Only an enquiry: on a confirmed booking, how long ago somebody last touched
// it says nothing useful.
const showTouched = computed(() => ['new', 'in_conversation', 'possible', 'quoted'].includes(props.event.stage))

const touched = computed(() => {
  // Calendar days rather than an elapsed duration divided by 86400, which is
  // off by one for anything touched in the evening.
  const days = differenceInCalendarDays(
    props.today ?? new Date(),
    parseISO(props.event.lastTouchedAt),
  )

  return t('bookings.list.touched', { count: days }, days)
})

const spineClasses = computed(() => {
  switch (props.event.stage) {
    case 'confirmed':
    case 'completed':
    case 'closed':
      return 'bg-accent'
    case 'provisional':
      return 'bg-border-accent-soft'
    case 'possible':
    case 'quoted':
      return 'bg-warning'
    default:
      return 'bg-border-strong'
  }
})
</script>

<style scoped>
/* The name follows the row into the accent, so a pointed-at row says so in
   words as well as in fill, which is the app's hover rule everywhere else. */
.booking-row:hover .booking-row__name {
  color: var(--accent-text);
}
</style>
