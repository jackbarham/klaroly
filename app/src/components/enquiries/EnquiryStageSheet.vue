<template>
  <AnchoredSheet
    ref="sheet"
    v-model:open="open"
    :label="title"
    :anchor-to="anchorTo"
    align="right"
    width-class="lg:w-88"
  >
    <div class="mb-3 flex min-h-9 items-center gap-2">
      <button
        v-if="view === 'reasons'"
        class="chip shrink-0 focus-visible:focus-ring"
        type="button"
        @click="showStages"
      >
        {{ t('enquiries.stage_sheet.back') }}
      </button>
      <p class="min-w-0 truncate text-body font-medium text-text-strong">
        {{ title }}
      </p>
    </div>

    <div v-if="view === 'stages'">
      <button
        v-for="stage in liveStages"
        :key="stage"
        :class="pickClasses"
        type="button"
        :aria-current="enquiry?.stage === stage ? 'true' : undefined"
        @click="choose(stage)"
      >
        <span class="min-w-0 grow">
          <span class="block text-body font-medium text-text-strong">{{ t(`bookings.stage.${stage}`) }}</span>
          <span class="block text-meta text-text-muted">{{ t(`enquiries.stage_hint.${stage}`) }}</span>
        </span>
        <Icon
          v-if="enquiry?.stage === stage"
          name="chevron-right"
          class="size-5 shrink-0 text-accent-text"
        />
      </button>

      <!--
        The rule separates moving an enquiry along from ending it. Above it are
        the four places it can still go; below it are the two ways it stops
        being an enquiry, and neither is undone by tapping the next one up.
      -->
      <hr class="my-2 border-border">

      <button
        :class="pickClasses"
        type="button"
        @click="choose('provisional')"
      >
        <span class="min-w-0 grow">
          <span class="block text-body font-medium text-accent-text">{{ t('enquiries.stage_sheet.convert') }}</span>
          <span class="block text-meta text-text-muted">{{ t('enquiries.stage_sheet.convert_hint') }}</span>
        </span>
      </button>

      <button
        :class="pickClasses"
        type="button"
        @click="showReasons"
      >
        <span class="min-w-0 grow">
          <span class="block text-body font-medium text-text-strong">{{ t('enquiries.stage_sheet.end') }}</span>
          <span class="block text-meta text-text-muted">{{ t('enquiries.stage_sheet.end_hint') }}</span>
        </span>
      </button>
    </div>

    <!--
      The second view, inside the same panel rather than as a second sheet: it
      is a step in one decision, and a panel that closes and reopens somewhere
      else loses the thread of it.
    -->
    <div v-else>
      <template
        v-for="side in sides"
        :key="side.key"
      >
        <p class="mt-3 mb-1 text-caption font-medium tracking-wide text-text-muted uppercase first:mt-0">
          {{ t(side.labelKey) }}
        </p>
        <button
          v-for="reason in side.reasons"
          :key="reason"
          :class="pickClasses"
          type="button"
          @click="end(reason)"
        >
          <span class="block min-w-0 grow text-body text-text-strong">{{ t(`enquiries.lost_reason.${reason}`) }}</span>
        </button>
      </template>
    </div>
  </AnchoredSheet>
</template>

<script setup lang="ts">
// The stage change, which is the interaction the whole feature turns on.
//
// Business logic 5.1: "Moving it to Possible is the moment it starts appearing
// on the calendar as a soft hold, and that is the single interaction the whole
// feature turns on. It has to be one tap from the enquiry row, with no form in
// the way, or she will not do it."
//
// Two views in one panel. The first is the four live stages, a rule, and the
// two ways an enquiry stops being one. The second is the nine reasons under
// two headings, and **the heading carries the side**, so no reason has to name
// who did it and the two rows both reading "Another reason" are not a
// duplication: which heading a reason sits under is the fact being recorded.
//
// Endings are two taps and that is deliberate. 5.1 asks for one tap to
// Possible; nothing asks for one tap to an ending, and one reached by a
// mis-tap is worse than one that takes a second tap.
import { computed, ref, useTemplateRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import Icon from '@/components/ui/Icon.vue'
import { liveStages } from '@/lib/enquiryList'
import type { BookingStage } from '@/types/bookings'
import type { Enquiry, LostReason } from '@/types/enquiries'

const props = defineProps<{
  // The enquiry being moved, or null when nothing is open.
  enquiry: Enquiry | null
  // The pill that opened this.
  anchorTo?: HTMLElement | null
}>()

const emit = defineEmits<{
  move: [enquiry: Enquiry, stage: BookingStage, reason: LostReason | null]
}>()

const { t } = useI18n()

const open = defineModel<boolean>('open', { required: true })

const sheet = useTemplateRef<{ refocus: () => Promise<void> }>('sheet')

const view = ref<'stages' | 'reasons'>('stages')

// Always the first view on the way in, so an ending abandoned last time does
// not reappear as the first thing the next enquiry offers.
watch(open, (isOpen) => {
  if (isOpen) {
    view.value = 'stages'
  }
})

const title = computed(() => (
  view.value === 'reasons'
    ? t('enquiries.stage_sheet.reasons_title')
    : t('enquiries.stage_sheet.title', { name: props.enquiry?.client_name ?? '' })
))

// The nine reasons under the two headings, in the order the enum declares
// them, so the app and the API agree about which side a reason is on.
const sides: { key: string, labelKey: string, reasons: LostReason[] }[] = [
  {
    key: 'client',
    labelKey: 'enquiries.stage_sheet.side_client',
    reasons: ['went_elsewhere', 'too_expensive', 'wedding_off', 'no_reply', 'client_other'],
  },
  {
    key: 'artist',
    labelKey: 'enquiries.stage_sheet.side_artist',
    reasons: ['already_booked', 'too_far', 'not_right_fit', 'artist_other'],
  },
]

const pickClasses = 'flex w-full min-h-11 items-center gap-3 rounded-control px-3 py-2 text-left transition-colors hover:bg-surface-sunken focus-visible:focus-ring'

/**
 * Swapping the view has to move focus, and this is the rule the prototype
 * found the hard way.
 *
 * Going back from the reasons view hides the Back button. If that button had
 * focus, focus falls to the body, the panel's own keydown handler stops seeing
 * anything, and **Escape silently stops closing the sheet**. The same is true
 * on the way in, where the button that was tapped is replaced by nine others.
 *
 * refocus() is useDialogBehaviour's, handed on by AnchoredSheet. The mechanism
 * stays in the composable, which already knows how to find the first focusable
 * thing in a panel; the decision stays here, because this is the only thing
 * that knows its own contents changed.
 */
async function swap(to: 'stages' | 'reasons'): Promise<void> {
  view.value = to

  await sheet.value?.refocus()
}

function showStages(): void {
  void swap('stages')
}

function showReasons(): void {
  void swap('reasons')
}

function choose(stage: BookingStage): void {
  const enquiry = props.enquiry

  open.value = false

  // Tapping the stage it is already at is a no-op rather than a write: it
  // would move last_touched_at, and the top of a list ordered by neglect is
  // not somewhere to lose a row by looking at it.
  if (enquiry && enquiry.stage !== stage) {
    emit('move', enquiry, stage, null)
  }
}

function end(reason: LostReason): void {
  const enquiry = props.enquiry

  open.value = false

  if (enquiry) {
    emit('move', enquiry, 'lost', reason)
  }
}
</script>
