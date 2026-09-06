<template>
  <div class="sticky stick-top z-10 flex items-center gap-2 bg-surface px-6 pt-1 pb-3 @split:px-4">
    <div class="relative min-w-0 grow">
      <label
        class="sr-only"
        :for="fieldId"
      >{{ t('enquiries.filter.label') }}</label>

      <Icon
        name="search"
        class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-text-placeholder"
      />

      <!--
        An ordinary search input, with no combobox role and nothing pointing at
        anything. The contacts field is a combobox because its list is a
        listbox it arrows a virtual cursor through; this list cannot be one,
        because its rows carry a control. See EnquiryList.vue.
      -->
      <input
        :id="fieldId"
        v-model="query"
        :class="[controlClasses, edgeClasses(false), 'h-12 pl-11 text-control']"
        type="search"
        autocomplete="off"
        autocapitalize="none"
        spellcheck="false"
        :placeholder="t('enquiries.filter.placeholder')"
        @keydown="onKeydown"
      >
    </div>

    <button
      ref="trigger"
      class="flex h-12 shrink-0 items-center gap-1.5 rounded-control border border-border-strong px-4 text-body font-medium text-text-strong transition-colors hover:bg-surface-sunken focus-visible:focus-ring"
      type="button"
      aria-haspopup="dialog"
      :aria-expanded="menuOpen"
      :aria-label="t('enquiries.filter.view_action_label')"
      @click="menuOpen = true"
    >
      {{ t('enquiries.filter.view_action') }}
      <Icon
        name="chevron-down"
        class="size-5 text-text-muted"
      />
    </button>

    <EnquiryViewMenu
      v-model:open="menuOpen"
      :anchor-to="trigger"
    />
  </div>
</template>

<script setup lang="ts">
// The filter field and the button that opens the view menu.
//
// It is a FILTER and not a search. It narrows rows already in memory,
// instantly, with no request behind it, which is why there is no debounce, no
// spinner and no minimum length. The whole list arrives in one payload for
// exactly this reason.
//
// The menu lives in here rather than in the screen because the button it hangs
// under is in here, and passing a template ref up a level so that a sibling
// can measure it is how two components end up owning one piece of geometry.
import { ref, useId, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import Icon from '@/components/ui/Icon.vue'
import EnquiryViewMenu from '@/components/enquiries/EnquiryViewMenu.vue'
import { controlClasses, edgeClasses } from '@/components/form/field'

const { t } = useI18n()

const query = defineModel<string>('query', { required: true })

const fieldId = useId()
const menuOpen = ref(false)
const trigger = useTemplateRef<HTMLElement>('trigger')

// Escape empties the field rather than doing nothing, which is what a filter
// box is expected to do and what saves a long press on the backspace key.
function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape' && query.value !== '') {
    event.preventDefault()
    query.value = ''
  }
}
</script>
