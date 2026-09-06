<template>
  <div
    role="list"
    :aria-label="t('home.attention.title')"
  >
    <!--
      Each band is its own wrapper, and it is not decoration. A sticky element
      is bounded by its containing block, so a heading stuck to the scroller
      rather than to its own band would still be pinned at the top of the screen
      while the artist reads the money figures two blocks below. That is
      decision 205's lesson applied before it can bite.
    -->
    <div
      v-for="group in groups"
      :key="group.party"
    >
      <h3 class="sticky stick-top z-2 border-y border-border bg-surface-sunken px-6 py-2 text-caption font-medium tracking-wide text-text-muted uppercase @split:px-4">
        {{ t(group.labelKey, { count: group.rows.length }, group.rows.length) }}
      </h3>

      <ul role="list">
        <AttentionRow
          v-for="row in group.rows"
          :key="row.booking_id"
          :row="row"
          :today="today"
        />
      </ul>
    </div>
  </div>
</template>

<script setup lang="ts">
// The attention rows, capped and grouped. **One component, drawn in two
// places**: the preview on /home and the full list on /attention.
//
// **The band headings count what is DRAWN, not the account total.** The preview
// says "2 need you" where the account has four, and the heading's "See all 8"
// carries the total. A heading reading "4 need you" above two rows reads as a
// broken list.
//
// That is also the reason /home renders this component TWICE rather than once
// with a limit the parent computes. The cap is not applied at lg at all, so the
// two widths need different headings, not the same heading with rows clipped:
// "2 need you" below the split and "4 need you" above it is different text, and
// no amount of CSS on one rendered list can produce it. So both are in the DOM
// and the container query picks one, which is also the arrangement the
// prototype measured. Anybody tempted to collapse it to one render should read
// that sentence again rather than the duplication.
//
// It has no roving tabindex and no arrow keys, unlike EnquiryList. That pattern
// exists because tabbing through forty rows before reaching anything after them
// is worse than useless; this list is four rows on /home and the artist's open
// workload on /attention. Worth revisiting if /attention routinely runs long.
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AttentionRow from '@/components/home/AttentionRow.vue'
import { attentionGroups } from '@/lib/homeList'
import type { AttentionRow as Row } from '@/types/home'

const props = defineProps<{
  rows: Row[]
  today: Date
  // How many rows to draw, or null for all of them. /attention always passes
  // null; /home passes the artist's preview count below the split and null
  // above it.
  limit: number | null
}>()

const { t } = useI18n()

// **Cut first, group second.** The endpoint returns decision 217's precedence,
// so the first N are the N most urgent. Grouping first and cutting each group
// gives four of the artist's own rows and no client group at all, so an overdue
// balance could never reach the preview. That was a real bug found by building
// the prototype and the order of those two steps is the whole fix; it lives in
// src/lib/homeList.ts so it can be tested without mounting this.
const groups = computed(() => attentionGroups(props.rows, props.limit))
</script>
