<template>
  <li
    class="enquiry-row relative flex min-h-16 items-start gap-3 border-b border-border px-6 py-3 transition-colors @split:px-4"
    :class="current ? 'bg-accent-subtle border-accent' : 'hover:bg-row-hover'"
  >
    <span class="min-w-0 grow">
      <!--
        The link covers the row through a stretched pseudo-element rather than
        wrapping it, because the stage pill has to sit on top of it and a
        control inside a link is the same invalid nesting a control inside a
        button is. The pill raises itself out of the link's way with a z-index.
      -->
      <RouterLink
        :id="rowId"
        class="enquiry-row__link block truncate text-control font-medium text-text-strong transition-colors focus-visible:focus-ring focus-visible:-outline-offset-2"
        :tabindex="focusable ? 0 : -1"
        :aria-current="current ? 'true' : undefined"
        :to="{ name: 'enquiry', params: { id: enquiry.id } }"
      >{{ enquiry.client_name }}</RouterLink>

      <span class="block truncate text-meta text-text-muted">{{ whenAndWhere(enquiry, today, t) }}</span>

      <span
        v-if="showSource && sourceLine"
        class="block truncate text-meta text-text-subtle"
      >{{ sourceLine }}</span>

      <span
        v-if="lostReason"
        class="block truncate text-meta text-text-subtle"
      >{{ t(`enquiries.lost_reason.${lostReason}`) }}</span>

      <!--
        The clash line. It is not a warning and it prevents nothing: a Saturday
        with four enquiries on it is a Saturday where three of them will book
        somebody else within a fortnight, and the one rung today is the one
        that converts. It wears the warning family because it is the only thing
        on this row besides the staleness figure that asks for attention.
      -->
      <span
        v-if="clash"
        class="block truncate text-meta font-medium text-warning-text"
      >{{ t(clash.key, clash.count) }}</span>
    </span>

    <span class="flex shrink-0 flex-col items-end gap-1">
      <!--
        The pill IS the control (decision 2026-09-06.1802). Making the thing
        the eye already goes to tappable costs nothing on the row: a named
        advance chip beside it truncated five more second lines out of fourteen
        at 375px.

        The click is stopped so it never reaches the row's link, and the button
        is lifted above the stretched link that covers the row.
      -->
      <button
        v-if="settable"
        ref="pill"
        class="relative z-1 focus-visible:focus-ring"
        type="button"
        aria-haspopup="dialog"
        :aria-label="t('enquiries.row.stage_action', { stage: t(`bookings.stage.${enquiry.stage}`) })"
        @click.stop.prevent="emit('stage', enquiry, pillElement)"
      >
        <StatusPill :tone="stageTone">
          {{ t(`bookings.stage.${enquiry.stage}`) }}
          <Icon
            name="chevron-down"
            class="-mr-0.5 ml-0.5 inline size-4 align-text-bottom"
          />
        </StatusPill>
      </button>

      <StatusPill
        v-else
        :tone="stageTone"
      >
        {{ lostLabel }}
      </StatusPill>

      <!--
        The total and the staleness figure share a line rather than stacking,
        or a quoted row is three deep on the right and two on the left.
      -->
      <span class="flex items-baseline gap-2 text-meta whitespace-nowrap">
        <span
          v-if="total !== null"
          class="text-text-muted tabular-nums"
        >{{ total }}</span>
        <span :class="cold ? 'font-medium text-warning-text' : 'text-text-subtle'">{{ t(ago.key, ago.count) }}</span>
      </span>
    </span>
  </li>
</template>

<script setup lang="ts">
// One enquiry in the list.
//
// **It is a listitem and not an option**, which is the one thing on this
// screen not to copy from contacts, and the reason is the pill. Business logic
// 5.1 puts a control inside the row, and an ARIA option may not contain
// interactive content: a button inside an option is either flattened away or
// stops the parent being an option, and which one a browser does is not ours
// to choose. The listbox pattern could not reach the pill anyway, because
// aria-activedescendant moves a virtual cursor and a control needs real focus.
// See EnquiryList.vue, which carries the whole of that reasoning.
import { computed, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import Icon from '@/components/ui/Icon.vue'
import StatusPill, { type PillTone } from '@/components/ui/StatusPill.vue'
import { agoKey, clashLine, daysSince, isCold, whenAndWhere } from '@/lib/enquiryList'
import type { Enquiry } from '@/types/enquiries'

const props = defineProps<{
  enquiry: Enquiry
  today: Date
  showSource: boolean
  showTotals: boolean
  showClashes: boolean
  // The DOM id the list uses to move focus, built from the list's own.
  rowId: string
  // Whether this row is the one the roving tabindex is parked on. Exactly one
  // row in the list is, so Tab lands in the list once rather than walking
  // every row in it.
  focusable: boolean
  // Whether this enquiry's detail is open beside the list.
  current: boolean
}>()

const emit = defineEmits<{
  // The pill was tapped: the row hands up the enquiry and the element to hang
  // the sheet under, because the sheet belongs to the screen and the geometry
  // belongs to the button.
  stage: [enquiry: Enquiry, anchor: HTMLElement | null]
}>()

const { t, n } = useI18n()

const pill = useTemplateRef<HTMLElement>('pill')
const pillElement = computed(() => pill.value ?? null)

// A lost enquiry has ended, so there is nothing to move it to from here: its
// pill says which of the two endings it was and is not a control.
const settable = computed(() => props.enquiry.stage !== 'lost')

const lostReason = computed(() => (props.enquiry.stage === 'lost' ? props.enquiry.lost_reason : null))

const lostLabel = computed(() => (
  props.enquiry.lost_side ? t(`enquiries.lost_pill.${props.enquiry.lost_side}`) : t('bookings.stage.lost')
))

/**
 * Colour on this row means attention, not stage.
 *
 * So warning and danger are reserved for the staleness figure and the clash
 * line, and the stages take the quieter families. New is the accent because
 * "nobody has looked at this" is the one stage that is a call to act; the rest
 * are neutral, info and success in pipeline order. An earlier version had
 * Possible on warning and it read as an alarm about a good thing:
 * --warning-text is --color-warning-800, which reads red, so anything wearing
 * it reads as a problem.
 */
const toneByStage: Partial<Record<Enquiry['stage'], PillTone>> = {
  new: 'accent',
  in_conversation: 'neutral',
  possible: 'info',
  quoted: 'success',
  lost: 'neutral',
}

const stageTone = computed(() => toneByStage[props.enquiry.stage] ?? 'neutral')

const cold = computed(() => isCold(props.enquiry))

const ago = computed(() => agoKey(daysSince(props.enquiry.last_touched_at, props.today)))

const clash = computed(() => (
  props.showClashes && props.enquiry.stage !== 'lost' ? clashLine(props.enquiry.clash) : null
))

// How it arrived. A captured enquiry names the booking it was captured at,
// because "met at Elspeth Rowntree's wedding" is the useful half of that fact
// and "captured at an event" is not.
const sourceLine = computed(() => {
  const booking = props.enquiry.source_booking

  if (booking) {
    return t('enquiries.source.met_at', { name: booking.client_name })
  }

  return props.enquiry.source ? t(`enquiries.source.${props.enquiry.source}`) : null
})

/**
 * The total, or nothing.
 *
 * Null and zero are different facts and the row says so: an enquiry nobody has
 * priced carries no figure at all, and one priced at nothing carries £0. The
 * API sends null for the first, which is the distinction it was made nullable
 * for. "No price yet" belongs on the detail, where there is room for a
 * sentence; a row with a price and a row without differ by the presence of the
 * figure.
 */
const total = computed(() => {
  const minor = props.enquiry.total_minor

  if (!props.showTotals || minor === null) {
    return null
  }

  return n(minor / 100, {
    key: minor % 100 === 0 ? 'currency_whole' : 'currency',
    currency: props.enquiry.currency,
  })
})
</script>

<style scoped>
/* The link covers the whole row, so a tap anywhere but the pill opens the
   enquiry. It is a pseudo-element rather than a wrapping anchor because the
   pill is a button and a button inside a link is the same invalid nesting a
   button inside a button is. */
.enquiry-row__link::after {
  content: '';
  position: absolute;
  inset: 0;
}

.enquiry-row:hover .enquiry-row__link {
  color: var(--accent-text);
}
</style>
