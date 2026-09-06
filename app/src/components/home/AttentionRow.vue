<template>
  <li class="relative flex min-h-16 items-center gap-3 border-b border-border px-6 py-3 transition-colors hover:bg-row-hover @split:px-4">
    <span class="min-w-0 grow">
      <!--
        The row is a LINK and not a button (decision 240). Nothing on it is
        interactive today, so a button would work; the two inline writes that
        are coming, Hold the date and Snooze, cannot live inside one, and a row
        whose markup has to change to gain a control is a row that gets
        rebuilt. Adding them is then one element beside this span rather than a
        rewrite.
      -->
      <RouterLink
        class="attention-row__link block truncate text-control font-medium text-text-strong transition-colors focus-visible:focus-ring focus-visible:-outline-offset-2"
        :to="{ name: 'booking', params: { id: row.booking_id } }"
      >{{ t(sentenceKey(row, today), { name: row.client_name }) }}</RouterLink>

      <span
        v-if="detail"
        class="block truncate text-meta text-text-muted"
      >
        {{ t(detail.key, detail.values, detail.count ?? 1) }}<!--
          **The only thing on this screen that wears a colour is money that is
          genuinely late.** Two things were drawn and removed for this rule and
          both would be added back by somebody who had not watched them fail: a
          coloured dot per row, which the band heading two lines above already
          says, and a call time in warning colour, which calls an early start a
          fault. --warning-text reads red, so anything wearing it claims a
          problem.
        --><template v-if="late"> · <span class="font-medium text-danger-text">{{ t('home.attention.late', { count: late }, late) }}</span></template>
      </span>
    </span>

    <Icon
      name="chevron-right"
      class="size-5 shrink-0 text-text-subtle"
      aria-hidden="true"
    />
  </li>
</template>

<script setup lang="ts">
// One row of the attention block: a sentence and a detail line, both built
// here from the row's fields and the locale file.
//
// **The endpoint sends no copy.** A server that writes UI copy is a server that
// has to be redeployed to fix a typo, and the wording is British English in
// src/locales/en-GB.json where every other string is.
//
// Every day figure is derived at render from the instants the payload carries,
// against the ACCOUNT's today rather than the device's: the server already
// decided what is overdue using that day, so a phone in another timezone doing
// its own sums would put a number on the row that the money block disagrees
// with.
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import Icon from '@/components/ui/Icon.vue'
import { daysLate, detailLine, isLate, sentenceKey } from '@/lib/homeList'
import { formatMoney } from '@/lib/money'
import type { AttentionRow } from '@/types/home'

const props = defineProps<{
  row: AttentionRow
  today: Date
}>()

const { n, t } = useI18n()

const detail = computed(() => detailLine(
  props.row,
  props.today,
  t,
  (minor, currency) => formatMoney(n, minor, currency),
))

// The days-late figure, drawn separately from the rest of the line because it
// is the one fragment that takes a colour. Nought when the row is not about
// money, which is most of them.
const late = computed(() => {
  if (!isLate(props.row, props.today) || props.row.due_on === null) {
    return 0
  }

  return daysLate(props.row.due_on, props.today)
})
</script>

<style scoped>
/* The link covers the whole row, so a tap anywhere opens the booking. It is a
   pseudo-element rather than a wrapping anchor for the reason the enquiries row
   gives: when the two inline writes land they are buttons, and a button inside
   a link is the same invalid nesting a button inside a button is. */
.attention-row__link::after {
  content: '';
  position: absolute;
  inset: 0;
}
</style>
