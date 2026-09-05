<template>
  <Sheet
    v-model:open="open"
    :label="t('create.title')"
  >
    <p class="px-4 pb-2 text-sm font-medium text-text-muted">
      {{ t('create.title') }}
    </p>
    <ul>
      <li
        v-for="row in rows"
        :key="row.key"
      >
        <button
          class="flex w-full items-center gap-4 rounded-control px-4 py-4 text-left text-text-strong hover:bg-surface-sunken focus-visible:focus-ring"
          type="button"
          @click="choose"
        >
          <Icon
            :name="row.icon"
            class="h-5 w-5 text-text-muted"
          />
          {{ t(row.labelKey) }}
        </button>
      </li>
    </ul>
  </Sheet>
</template>

<script setup lang="ts">
// What the New button and the tab bar's plus button both open. One
// component: on a phone the sheet slides up from the bottom edge, on a wide
// screen the same panel is a menu under the sidebar's New button, and which
// one it is comes from the width alone. See Sheet.vue.
import { useI18n } from 'vue-i18n'
import type { IconName } from '@/components/ui/Icon.vue'

const open = defineModel<boolean>('open', { required: true })

const { t } = useI18n()

const rows: { key: string, labelKey: string, icon: IconName }[] = [
  { key: 'enquiry', labelKey: 'create.enquiry', icon: 'enquiries' },
  { key: 'booking', labelKey: 'create.booking', icon: 'calendar' },
  { key: 'contact', labelKey: 'create.contact', icon: 'contacts' },
]

function choose(): void {
  // TODO: the three rows create an enquiry, a booking and a contact. They are
  // wired up by the prompt that builds the first real section, Bookings.
  open.value = false
}
</script>
