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

    <div
      v-for="(row, index) in switches"
      :key="row.field"
      class="flex min-h-11 items-center justify-between gap-4"
      :class="index > 0 ? 'mt-2 border-t border-border pt-2' : ''"
    >
      <span
        :id="row.labelId"
        class="text-body font-medium text-text-strong"
      >{{ t(row.labelKey) }}</span>
      <ToggleSwitch
        :id="row.switchId"
        :model-value="settings[row.field]"
        :labelled-by="row.labelId"
        @update:model-value="(value) => contacts.update({ [row.field]: value })"
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
// The panel around it is ui/AnchoredSheet.vue: a bottom sheet below lg and a
// panel hanging under a measured button at lg. It is right-aligned here so that three hundred pixels of panel stay over
// the 400px list column rather than spilling across the detail beside it.
// Everything in this file is about the content.
import { computed, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import { segmentClasses, segmentOffClasses, segmentOnClasses } from '@/components/form/field'
import ToggleSwitch from '@/components/form/ToggleSwitch.vue'
import { useContactsStore } from '@/stores/contacts'
import type { LeadWith, SortMode, ViewSettings } from '@/lib/contactView'

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

const sortOptions: { value: SortMode, labelKey: string }[] = [
  { value: 'recent', labelKey: 'contacts.view.sort_recent' },
  { value: 'alpha', labelKey: 'contacts.view.sort_alpha' },
]

const leadOptions: { value: LeadWith, labelKey: string }[] = [
  { value: 'name', labelKey: 'contacts.view.lead_name' },
  { value: 'booking', labelKey: 'contacts.view.lead_booking' },
]

// The two switches as data rather than two copies of the same markup, the
// way the enquiries menu writes its four. A switch writes straight through to
// the store, which persists it, so there is no local copy that could be a
// setting behind what the list is drawing.
type SwitchField = Exclude<keyof ViewSettings, 'sort' | 'leadWith'>

const switchFields: { field: SwitchField, labelKey: string }[] = [
  { field: 'showInitials', labelKey: 'contacts.view.initials_label' },
  { field: 'showAmounts', labelKey: 'contacts.view.amounts_label' },
]

// The ids are made once rather than inside the loop, because useId may only be
// called during setup.
const switches = switchFields.map((row) => ({
  ...row,
  labelId: useId(),
  switchId: useId(),
}))
</script>
