<template>
  <AnchoredSheet
    v-model:open="open"
    :label="t('enquiries.view.title')"
    :anchor-to="anchorTo"
    align="right"
    width-class="lg:w-75"
  >
    <p
      :id="sortLabelId"
      class="mb-2 text-body font-medium text-text-strong"
    >
      {{ t('enquiries.view.sort_label') }}
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
        @click="enquiries.update({ sort: option.value })"
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
        @update:model-value="(value) => enquiries.update({ [row.field]: value })"
      />
    </div>
  </AnchoredSheet>
</template>

<script setup lang="ts">
// The five things that change how this list reads: how it is ordered, whether
// the source line, the quoted totals and the clash line are drawn, and whether
// the archive shows at all.
//
// It stays open while any of them change, which is the point: the list redraws
// underneath and each setting is judged by its effect rather than by its name.
//
// The panel is ui/AnchoredSheet.vue, right-aligned because the button that
// opens it sits at the right of a 400px list column and a left-aligned panel
// would spill across the detail beside it.
//
// Five is close to the limit for one menu. A sixth needs an argument.
import { computed, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import ToggleSwitch from '@/components/form/ToggleSwitch.vue'
import { useEnquiriesStore } from '@/stores/enquiries'
import type { EnquirySort, EnquiryViewSettings } from '@/lib/enquiryView'

defineProps<{
  // The button this hangs under at lg and up.
  anchorTo?: HTMLElement | null
}>()

const { t } = useI18n()
const enquiries = useEnquiriesStore()

const open = defineModel<boolean>('open', { required: true })

const settings = computed(() => enquiries.settings)

const sortLabelId = useId()

const sortOptions: { value: EnquirySort, labelKey: string }[] = [
  { value: 'staleness', labelKey: 'enquiries.view.sort_staleness' },
  { value: 'stage', labelKey: 'enquiries.view.sort_stage' },
  { value: 'date', labelKey: 'enquiries.view.sort_date' },
]

// The four switches as data rather than four copies of the same markup. A
// switch writes straight through to the store, which persists it, so there is
// no local copy that could be a setting behind what the list is drawing.
type SwitchField = Exclude<keyof EnquiryViewSettings, 'sort'>

const switchFields: { field: SwitchField, labelKey: string }[] = [
  { field: 'showSource', labelKey: 'enquiries.view.source_label' },
  { field: 'showTotals', labelKey: 'enquiries.view.totals_label' },
  { field: 'showClashes', labelKey: 'enquiries.view.clashes_label' },
  { field: 'showLost', labelKey: 'enquiries.view.lost_label' },
]

// The ids are made once rather than inside the loop, because useId may only be
// called during setup.
const switches = switchFields.map((row) => ({
  ...row,
  labelId: useId(),
  switchId: useId(),
}))

// The same segmented treatment the contacts menu uses, and the same deliberate
// departure from the quieter one in docs/style-guide.md: these sit inside a
// panel that is itself over a scrim, where the quieter version could not be
// read as chosen at a glance.
const segmentClasses = 'h-11 grow rounded-control text-body font-medium transition-colors focus-visible:focus-ring'
const segmentOnClasses = 'bg-accent text-text-on-accent'
const segmentOffClasses = 'text-text-muted hover:text-accent-text'
</script>
