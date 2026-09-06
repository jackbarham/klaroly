<template>
  <!--
    The empty-block rule, business logic 18.1: it disappears rather than showing
    an empty state. Attention is the one block with an all-clear line, because
    an empty attention list is a fact worth stating; an empty diary is not, and
    a "no bookings yet" card on every quiet fortnight is furniture on the
    most-used screen in the product.
  -->
  <section
    v-if="events.length > 0"
    aria-labelledby="next-heading"
  >
    <div class="mb-1 flex items-baseline justify-between gap-4 px-6 @split:px-4">
      <h2
        id="next-heading"
        class="text-lg font-semibold text-text-strong"
      >
        {{ t('home.next.title') }}
      </h2>

      <!--
        **Bookings, not Calendar.** Section 17 merged the calendar into Bookings
        because they are two views of one thing, so a link named Calendar would
        point at something that is not a destination.
      -->
      <RouterLink
        class="flex shrink-0 items-center gap-1 text-meta font-medium text-accent-text focus-visible:focus-ring"
        :to="{ name: 'bookings' }"
      >
        {{ t('home.next.all') }}
        <Icon
          name="chevron-right"
          class="size-4"
          aria-hidden="true"
        />
      </RouterLink>
    </div>

    <ul role="list">
      <NextUpRow
        v-for="event in shown"
        :key="event.event_id"
        :event="event"
        :today="today"
      />
    </ul>
  </section>
</template>

<script setup lang="ts">
// The next few events, business logic 18.2.
//
// **The endpoint sends six and this draws three**, which is the division of
// labour the API's own comment describes: sending exactly what one layout draws
// is how an endpoint becomes a screen's private API, so it errs above and the
// screen chooses. Measured rather than assumed: a row is about a hundred pixels
// at 375px, so six of them fill the screen on their own and push every
// attention row below the fold. Three plus the Bookings link is 18.2's "next
// two or three", and it is what leaves room for the block underneath.
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import NextUpRow from '@/components/home/NextUpRow.vue'
import Icon from '@/components/ui/Icon.vue'
import type { UpcomingEvent } from '@/types/home'

const props = defineProps<{
  events: UpcomingEvent[]
  today: Date
}>()

const { t } = useI18n()

const shown = computed(() => props.events.slice(0, 3))
</script>
