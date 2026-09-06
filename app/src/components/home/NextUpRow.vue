<template>
  <li class="relative flex min-h-16 items-center gap-3 border-b border-border px-6 py-3 transition-colors hover:bg-row-hover @split:px-4">
    <!--
      The date column. A weekday, a day number and a month, stacked, because
      "Sat 12 Sep" on one line at 375px takes room the name needs and the
      weekday is the part an artist reads first.
    -->
    <span
      class="flex w-11 shrink-0 flex-col items-center leading-tight"
      aria-hidden="true"
    >
      <span class="text-caption text-text-subtle uppercase">{{ parts.weekday }}</span>
      <span class="text-lg font-semibold text-text-strong">{{ parts.day }}</span>
      <span class="text-caption text-text-subtle uppercase">{{ parts.month }}</span>
    </span>

    <span class="min-w-0 grow">
      <span class="flex items-center gap-2">
        <RouterLink
          class="next-row__link truncate text-control font-medium text-text-strong transition-colors focus-visible:focus-ring focus-visible:-outline-offset-2"
          :to="{ name: 'booking', params: { id: event.booking_id } }"
        >{{ event.client_name }}</RouterLink>

        <StatusPill
          v-if="event.type === 'trial'"
          tone="neutral"
        >
          {{ t('home.next.trial') }}
        </StatusPill>
      </span>

      <span class="block truncate text-meta text-text-muted">{{ place }}</span>

      <span class="block truncate text-meta text-text-subtle">
        <!--
          The call time carries no colour. An early start is a fact about a
          Saturday and not a fault: the prototype drew 6:30 in warning colour
          and took it out, which is decision 183 arriving again.
        -->
        <template v-if="event.start_time">{{ event.start_time }}</template>
        <template v-if="travel"> · {{ t('home.next.travel', { count: travel }) }}</template>
        <!--
          **The party is drawn on main events only**, and that is a data
          problem rather than a layout choice: party_size is the whole
          booking's party, because party_members.event_id is nullable meaning
          "the main one" and nothing counts per event today. On a trial it
          would say "Bride and 6" for an appointment that is usually one
          person, which is a visibly wrong number rather than a missing one.
        -->
        <template v-if="party"> · {{ party }}</template>
      </span>
    </span>

    <StatusPill
      tone="neutral"
      class="shrink-0"
    >
      {{ t(countdown.key, { count: countdown.count }, countdown.count) }}
    </StatusPill>
  </li>
</template>

<script setup lang="ts">
// One event in Next up: the "what am I doing on Saturday" answer, business
// logic 18.2.
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import StatusPill from '@/components/ui/StatusPill.vue'
import { countdownKey, dateParts, daysUntil, placeKey, showsParty, travelMinutes } from '@/lib/homeList'
import type { UpcomingEvent } from '@/types/home'

const props = defineProps<{
  event: UpcomingEvent
  today: Date
}>()

const { t } = useI18n()

const parts = computed(() => dateParts(props.event.date, t))

const countdown = computed(() => countdownKey(daysUntil(props.event.date, props.today)))

// Four cases and not three (decision 228): a null location_type is "nobody has
// said", which is a real state for an early enquiry rather than a missing
// value. The venue is shortened by the helper the two list screens already
// share, so "The Old Corn Exchange, Saffron Walden" becomes "The Old Corn
// Exchange" rather than truncating at 375px.
const place = computed(() => {
  const found = placeKey(props.event)

  if (found.key === 'home.next.place.client' && found.venue === null) {
    return t('home.next.place.client_unknown')
  }

  return t(found.key, { venue: found.venue ?? '' })
})

const travel = computed(() => travelMinutes(props.event))

const party = computed(() => {
  if (!showsParty(props.event)) {
    return null
  }

  const size = props.event.party_size ?? 0

  // "Bride" alone rather than "Bride and 0", because a party of one is the
  // bride and nothing else is worth saying about it.
  return size <= 1
    ? t('home.next.party_one')
    : t('home.next.party', { count: size - 1 })
})
</script>

<style scoped>
/* The link covers the row, so a tap anywhere opens the booking. */
.next-row__link::after {
  content: '';
  position: absolute;
  inset: 0;
}
</style>
