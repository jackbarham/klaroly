<template>
  <div class="sticky stick-top z-10 flex items-center gap-2 bg-surface px-6 pt-1 pb-3 @split:px-4">
    <div class="relative min-w-0 grow">
      <label
        class="sr-only"
        :for="fieldId"
      >{{ t('contacts.filter.label') }}</label>

      <Icon
        name="search"
        class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-text-placeholder"
      />

      <input
        :id="fieldId"
        v-model="query"
        :class="[controlClasses, edgeClasses(false), 'h-12 pl-11 text-control']"
        type="text"
        autocomplete="off"
        autocapitalize="none"
        spellcheck="false"
        role="combobox"
        aria-expanded="true"
        :aria-controls="listId"
        :aria-activedescendant="activeId ?? undefined"
        :placeholder="t('contacts.filter.placeholder')"
        @keydown="onKeydown"
      >
    </div>

    <button
      ref="trigger"
      class="flex h-12 shrink-0 items-center gap-1.5 rounded-control border border-border-strong px-4 text-body font-medium text-text-strong transition-colors hover:bg-surface-sunken focus-visible:focus-ring"
      type="button"
      aria-haspopup="dialog"
      :aria-expanded="menuOpen"
      :aria-label="t('contacts.filter.view_action_label')"
      @click="menuOpen = true"
    >
      {{ t('contacts.filter.view_action') }}
      <Icon
        name="chevron-down"
        class="size-5 text-text-muted"
      />
    </button>

    <ContactViewMenu
      v-model:open="menuOpen"
      :anchor-to="trigger"
    />
  </div>
</template>

<script setup lang="ts">
// The filter field and the button that opens the view menu, in a bar that is
// always on screen.
//
// It is a FILTER and not a search. It narrows rows that are already in memory,
// instantly, and there is no request behind it, which is why there is no
// debounce, no spinner and no minimum length. The whole list arrives in one
// payload for exactly this reason.
//
// The field is a combobox and the list below is its listbox, because arrowing
// through results has to leave focus in the field: somebody who has typed
// "mui" and arrowed past the first Muirhead should be able to keep typing
// rather than tab back. So the arrow keys move a cursor the field points at
// with aria-activedescendant, and moving it never moves focus.
//
// The menu lives in here rather than in the screen because the button it hangs
// under is in here, and passing a template ref up a level so that a sibling
// can measure it is how two components end up owning one piece of geometry.
import { ref, useId, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import Icon from '@/components/ui/Icon.vue'
import ContactViewMenu from '@/components/contacts/ContactViewMenu.vue'
import { controlClasses, edgeClasses } from '@/components/form/field'

defineProps<{
  // The listbox this field drives, and the option in it the cursor is on.
  listId: string
  activeId: string | null
}>()

const emit = defineEmits<{
  // A step through the results, without focus leaving the field.
  move: [delta: number]
  // Open whatever the cursor is on.
  choose: []
}>()

const { t } = useI18n()

const query = defineModel<string>('query', { required: true })

const fieldId = useId()
const menuOpen = ref(false)
const trigger = useTemplateRef<HTMLElement>('trigger')

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'ArrowDown') {
    event.preventDefault()
    emit('move', 1)

    return
  }

  if (event.key === 'ArrowUp') {
    event.preventDefault()
    emit('move', -1)

    return
  }

  if (event.key === 'Enter') {
    event.preventDefault()
    emit('choose')

    return
  }

  // Escape empties the field rather than doing nothing, which is what a filter
  // box is expected to do and what saves a long press on the backspace key.
  if (event.key === 'Escape' && query.value !== '') {
    event.preventDefault()
    query.value = ''
  }
}
</script>
