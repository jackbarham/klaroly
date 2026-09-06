<template>
  <div class="pb-8">
    <!--
      The way back to the list, and only where the list is not on screen. It
      answers the same container query the layout does rather than a viewport
      breakpoint, so it appears exactly when the list has gone and never while
      it is sitting beside this card.
    -->
    <RouterLink
      class="mb-2 inline-flex items-center gap-1 rounded-xs px-6 text-body text-text-muted transition-colors hover:text-accent-text focus-visible:focus-ring @split:hidden"
      :to="{ name: 'enquiries' }"
    >
      <Icon
        name="chevron-left"
        class="size-4"
      />
      {{ t('enquiries.detail.back') }}
    </RouterLink>

    <!--
      The header, which is 19.3's "always visible" block. Every field in it is
      on the list row already, so it draws the moment a row is tapped and does
      not wait for the second request.
    -->
    <div class="border-b border-border px-6 pt-4 pb-5 @split:px-0 @split:pt-0">
      <h2 class="text-title font-medium text-text-strong">
        {{ enquiry.client_name }}
      </h2>

      <p class="mt-1 text-body text-text-muted">
        {{ whenAndWhere(enquiry, today, t) }}
      </p>

      <div class="mt-3 flex flex-wrap items-center gap-2">
        <StatusPill :tone="stageTone">
          {{ stageLabel }}
        </StatusPill>
        <StatusPill
          v-if="enquiry.waiting_on"
          tone="warning"
        >
          {{ t(`bookings.waiting.${enquiry.waiting_on}`) }}
        </StatusPill>
        <span class="text-meta text-text-subtle">{{ t('enquiries.row.touched', { ago: t(ago.key, ago.count) }) }}</span>
      </div>

      <dl class="mt-4 grid gap-x-6 gap-y-2 text-body @split:grid-cols-2">
        <div class="flex justify-between gap-4 @split:block">
          <dt class="text-text-muted">
            {{ t('enquiries.detail.source_label') }}
          </dt>
          <dd class="text-text-strong">
            {{ sourceLine ?? t('enquiries.detail.nothing_here') }}
          </dd>
        </div>
        <div class="flex justify-between gap-4 @split:block">
          <dt class="text-text-muted">
            {{ t('enquiries.section.price') }}
          </dt>
          <!--
            Null and zero are different facts. "No price yet" is an enquiry
            nobody has quoted, which is most of them; "£0" is a job somebody is
            doing for nothing. Neither the total nor the stage could tell them
            apart before the API made this nullable.
          -->
          <dd
            class="text-text-strong"
            :class="total === null ? 'text-text-placeholder' : ''"
          >
            {{ total ?? t('enquiries.row.no_price') }}
          </dd>
        </div>
      </dl>

      <!--
        The two or three most likely next actions, which at these stages are
        move it along, build a price, and convert. None of them is built yet
        beyond the two that are stage changes.
      -->
      <div class="mt-4 flex flex-wrap gap-2">
        <AppButton
          v-for="action in actions"
          :key="action.key"
          :variant="action.primary ? 'primary' : 'secondary'"
          size="small"
          @click="emit('action', action.key)"
        >
          {{ t(`enquiries.detail.action_${action.key}`) }}
        </AppButton>
      </div>
    </div>

    <!--
      Business logic 5.5.1: the source is kept on the record, so that when an
      extraction is wrong the artist can see what it was working from. It is
      the difference between a name from four months ago and a conversation
      that can be picked up, and it is the one block worth keeping whatever
      else is on this screen.
    -->
    <section
      v-if="detail?.enquiry_message"
      class="border-b border-border px-6 py-5 @split:px-0"
    >
      <h3 class="mb-2 text-body font-medium text-text-strong">
        {{ t('enquiries.detail.said_title') }}
      </h3>
      <p class="text-body whitespace-pre-line text-text">
        {{ detail.enquiry_message }}
      </p>
    </section>

    <section
      v-for="section in sections"
      :key="section.key"
      class="border-b border-border px-6 py-4 @split:px-0"
    >
      <h3 class="text-body font-medium text-text-strong">
        {{ t(`enquiries.section.${section.key}`) }}
      </h3>
      <p class="mt-1 text-body text-text-muted">
        {{ summaryFor(section.key) }}
      </p>
    </section>

    <!--
      What the nine headings were being asked to teach costs one sentence
      instead, at the foot of the screen rather than as nine empty blocks above
      the fold.
    -->
    <p class="px-6 pt-5 text-meta text-text-subtle @split:px-0">
      {{ t('enquiries.detail.to_come') }}
    </p>
  </div>
</template>

<script setup lang="ts">
// One enquiry, which is the booking screen against a record where most of it
// is still empty.
//
// It is not a second layout. Business logic 4.3 is one bookings table with a
// stage column, so there is no enquiry detail to keep in step with a booking
// one, and what this renders is 19.3's header and summary. Which sections it
// draws is src/lib/enquirySections.ts, because the rule is about the stage and
// the feature map rather than about markup.
//
// The header draws from the list row, which carries every field in it, so
// tapping a row shows the name, the date and the stage at once and fills the
// rest in when the second request arrives. Making the artist wait to see what
// she just tapped is the thing this avoids.
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import AppButton from '@/components/ui/AppButton.vue'
import Icon from '@/components/ui/Icon.vue'
import StatusPill, { type PillTone } from '@/components/ui/StatusPill.vue'
import { agoKey, daysSince, whenAndWhere } from '@/lib/enquiryList'
import { sectionsFor } from '@/lib/enquirySections'
import type { FeatureMap } from '@/types/auth'
import type { Enquiry, EnquiryDetail } from '@/types/enquiries'

const props = defineProps<{
  // The list row, which is what draws immediately.
  enquiry: Enquiry
  // The second request's answer, or null while it is in flight.
  detail: EnquiryDetail | null
  features: FeatureMap | null
  today: Date
}>()

const emit = defineEmits<{
  action: [key: string]
}>()

const { t, n } = useI18n()

const stageLabel = computed(() => (
  props.enquiry.stage === 'lost' && props.enquiry.lost_side
    ? t(`enquiries.lost_pill.${props.enquiry.lost_side}`)
    : t(`bookings.stage.${props.enquiry.stage}`)
))

const toneByStage: Partial<Record<Enquiry['stage'], PillTone>> = {
  new: 'accent',
  in_conversation: 'neutral',
  possible: 'info',
  quoted: 'success',
  lost: 'neutral',
}

const stageTone = computed(() => toneByStage[props.enquiry.stage] ?? 'neutral')

const ago = computed(() => agoKey(daysSince(props.enquiry.last_touched_at, props.today)))

const sourceLine = computed(() => {
  const booking = props.enquiry.source_booking

  if (booking) {
    return t('enquiries.source.met_at', { name: booking.client_name })
  }

  return props.enquiry.source ? t(`enquiries.source.${props.enquiry.source}`) : null
})

const total = computed(() => {
  const minor = props.enquiry.total_minor

  if (minor === null) {
    return null
  }

  return n(minor / 100, {
    key: minor % 100 === 0 ? 'currency_whole' : 'currency',
    currency: props.enquiry.currency,
  })
})

const sections = computed(() => sectionsFor(props.enquiry.stage, props.features))

/**
 * The two or three most likely next actions, which change with the stage.
 *
 * A lost enquiry gets one: the way back. Everything else gets the move that
 * matters next and the conversion, because those are the two things 5.1 and
 * 5.3 say the artist actually does from here.
 */
const actions = computed(() => {
  const stage = props.enquiry.stage

  if (stage === 'lost') {
    return [{ key: 'reopen', primary: true }]
  }

  const list: { key: string, primary: boolean }[] = []

  if (stage === 'new' || stage === 'in_conversation') {
    list.push({ key: 'possible', primary: true })
  }

  if (stage === 'possible') {
    list.push({ key: 'quote', primary: true })
  }

  list.push({ key: 'convert', primary: stage === 'quoted' })

  return list
})

/**
 * What a section says when there is nothing in it.
 *
 * Every one of them is empty on an enquiry today, because none of the writes
 * behind them exists yet. The wording is the section's own "nothing here yet"
 * rather than one shared phrase, so that each says what is missing rather than
 * that something is.
 */
function summaryFor(key: string): string {
  if (key === 'dates') {
    return props.enquiry.event === null
      ? t('enquiries.empty.dates')
      : whenAndWhere(props.enquiry, props.today, t)
  }

  if (key === 'party') {
    const size = props.detail?.party_size ?? null

    if (size === null) {
      return t('enquiries.empty.party')
    }

    return size === 1 ? t('enquiries.detail.party_one') : t('enquiries.detail.party', { count: size })
  }

  if (key === 'price') {
    return total.value ?? t('enquiries.empty.price')
  }

  if (key === 'notes') {
    const notes = props.detail?.notes ?? []

    return notes.length > 0 ? notes[0].body : t('enquiries.empty.notes')
  }

  return t(`enquiries.empty.${key}`)
}
</script>
