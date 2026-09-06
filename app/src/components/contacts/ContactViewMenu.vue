<template>
  <AnchoredSheet
    v-model:open="open"
    :label="t('contacts.view.title')"
    :anchor-to="anchorTo"
    align="right"
    width-class="lg:w-75"
  >
    <p
      :id="sortLabelId"
      class="mb-2 text-body font-medium text-text-strong"
    >
      {{ t('contacts.view.sort_label') }}
    </p>
    <div
      class="mb-6 flex gap-1 rounded-control bg-surface-sunken p-1"
      role="group"
      :aria-labelledby="sortLabelId"
    >
      <button
        v-for="option in sortOptions"
        :key="option.value"
        :class="[segmentClasses, settings.sort === option.value ? segmentOnClasses : segmentOffClasses]"
        type="button"
        :aria-pressed="settings.sort === option.value"
        @click="contacts.update({ sort: option.value })"
      >
        {{ t(option.labelKey) }}
      </button>
    </div>

    <p
      :id="leadLabelId"
      class="mb-2 text-body font-medium text-text-strong"
    >
      {{ t('contacts.view.lead_label') }}
    </p>
    <div
      class="mb-6 flex gap-1 rounded-control bg-surface-sunken p-1"
      role="group"
      :aria-labelledby="leadLabelId"
    >
      <button
        v-for="option in leadOptions"
        :key="option.value"
        :class="[segmentClasses, settings.leadWith === option.value ? segmentOnClasses : segmentOffClasses]"
        type="button"
        :aria-pressed="settings.leadWith === option.value"
        @click="contacts.update({ leadWith: option.value })"
      >
        {{ t(option.labelKey) }}
      </button>
    </div>

    <div class="flex min-h-11 items-center justify-between gap-4">
      <span
        :id="initialsLabelId"
        class="text-body font-medium text-text-strong"
      >{{ t('contacts.view.initials_label') }}</span>
      <ToggleSwitch
        :id="initialsSwitchId"
        v-model="showInitials"
        :labelled-by="initialsLabelId"
      />
    </div>

    <div class="mt-2 flex min-h-11 items-center justify-between gap-4 border-t border-border pt-2">
      <span
        :id="amountsLabelId"
        class="text-body font-medium text-text-strong"
      >{{ t('contacts.view.amounts_label') }}</span>
      <ToggleSwitch
        :id="amountsSwitchId"
        v-model="showAmounts"
        :labelled-by="amountsLabelId"
      />
    </div>
  </AnchoredSheet>
</template>

<script setup lang="ts">
// The four things that change how this list reads: how it is sorted, which of
// a row's two lines is the strong one, whether initials are drawn and whether
// money is shown at all.
//
// It stays open while any of them change, which is the point: the list redraws
// underneath and each setting is judged by its effect rather than by its name.
//
// The panel around it is ui/AnchoredSheet.vue, which the month jump sheet also
// uses: a bottom sheet below lg and a panel hanging under a measured button at
// lg. It is right-aligned here so that three hundred pixels of panel stay over
// the 400px list column rather than spilling across the detail beside it.
// Everything in this file is about the content.
import { computed, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import ToggleSwitch from '@/components/form/ToggleSwitch.vue'
import { useContactsStore } from '@/stores/contacts'
import type { LeadWith, SortMode } from '@/lib/contactView'

defineProps<{
  // The button this hangs under at lg and up.
  anchorTo?: HTMLElement | null
}>()

const { t } = useI18n()
const contacts = useContactsStore()

const open = defineModel<boolean>('open', { required: true })

const settings = computed(() => contacts.settings)

const sortLabelId = useId()
const leadLabelId = useId()
const initialsLabelId = useId()
const initialsSwitchId = useId()
const amountsLabelId = useId()
const amountsSwitchId = useId()

const sortOptions: { value: SortMode, labelKey: string }[] = [
  { value: 'recent', labelKey: 'contacts.view.sort_recent' },
  { value: 'alpha', labelKey: 'contacts.view.sort_alpha' },
]

const leadOptions: { value: LeadWith, labelKey: string }[] = [
  { value: 'name', labelKey: 'contacts.view.lead_name' },
  { value: 'booking', labelKey: 'contacts.view.lead_booking' },
]

// A switch writes straight through to the store, which persists it, so there
// is no local copy that could be a setting behind what the list is drawing.
const showInitials = computed({
  get: () => contacts.settings.showInitials,
  set: (value: boolean) => contacts.update({ showInitials: value }),
})

const showAmounts = computed({
  get: () => contacts.settings.showAmounts,
  set: (value: boolean) => contacts.update({ showAmounts: value }),
})

// The selected segment is a filled accent carrying a white label. That is a
// deliberate departure from the segmented control in docs/style-guide.md,
// which is quieter: here the two groups sit inside a panel that is itself over
// a scrim, and the quieter treatment could not be read as chosen at a glance.
const segmentClasses = 'h-11 grow rounded-control text-body font-medium transition-colors focus-visible:focus-ring'
const segmentOnClasses = 'bg-accent text-text-on-accent'
const segmentOffClasses = 'text-text-muted hover:text-accent-text'
</script>
