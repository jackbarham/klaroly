<template>
  <div class="space-y-2">
    <!--
      Two shapes for one field, and which one a control wants is decided by
      how it reads rather than by the screen it is on.

      A tick box reads as the sentence beside it, so the label becomes the row
      and wraps the box. That is what puts the words in the 44px hit area and
      gives them the row's hover, and the box is named by that same label, so
      nothing here points at it a second time.
    -->
    <label
      v-if="inline"
      :id="labelId"
      :class="optionRowClasses"
      :for="fieldId"
    >
      <slot v-bind="control" />
      <span>{{ label }}</span>
    </label>

    <!--
      Everything else reads as a heading with a control under it. The label
      carries "for" so that clicking it focuses a real input, and a control
      that is not a labelable element, such as the toggle switch or a radio
      group, ignores "for" and points at the label's id with aria-labelledby
      instead, which is why the id is handed to the slot too.
    -->
    <template v-else>
      <label
        :id="labelId"
        class="block text-sm font-medium text-text"
        :for="fieldId"
      >{{ label }}</label>

      <slot v-bind="control" />
    </template>

    <p
      v-if="hint"
      :id="hintId"
      class="text-sm text-text-muted"
    >
      {{ hint }}
    </p>

    <!--
      What a control's live status says out loud. It is announced when it
      changes rather than described with the control, because it is the
      answer to what has just been typed, not part of what the field is for.
    -->
    <p
      v-if="statusMessage"
      class="sr-only"
      role="status"
    >
      {{ statusMessage }}
    </p>

    <p
      v-if="error"
      :id="errorId"
      class="text-sm font-medium text-danger-text"
    >
      {{ error }}
    </p>
  </div>
</template>

<script setup lang="ts">
// The wrapper that owns everything around a form control: the label, the
// hint, the error and the ids that tie the three together. The controls
// take those through ControlProps and do no wiring of their own, so a field
// is written as one line:
//
//   <FormField v-slot="field" label="..."><TextInput v-bind="field" v-model="x" /></FormField>
//
// inline is the tick box's shape: the label becomes the row and wraps the
// control instead of sitting above it, so a checkbox is written as a field
// like everything else and still reads as the sentence beside its box.
//
// statusMessage is the words for a control that shows a live check as
// someone types, which TextInput draws as a tick or a cross. The mark is
// inside the control's box, so the control draws it; the words belong out
// here with the hint and the error, so the field says them.
import { computed, useId } from 'vue'
import { optionRowClasses } from '@/components/form/field'

const props = defineProps<{
  label: string
  hint?: string
  error?: string
  statusMessage?: string
  inline?: boolean
}>()

// Vue generates an id that is unique in the page, so a field can appear
// twice on one page without the two labels pointing at the same control.
const fieldId = useId()
const labelId = `${fieldId}-label`
const hintId = `${fieldId}-hint`
const errorId = `${fieldId}-error`

const invalid = computed(() => Boolean(props.error))

// The hint and the error are both announced with the control, in that order.
const describedBy = computed(() => {
  const ids: string[] = []

  if (props.hint) {
    ids.push(hintId)
  }

  if (props.error) {
    ids.push(errorId)
  }

  return ids.length > 0 ? ids.join(' ') : undefined
})

// What the slot binds onto whatever control sits in it. The inline shape
// hands over no labelledBy: its label wraps the control and names it through
// "for", and a second name on top of that is one label too many.
const control = computed(() => ({
  id: fieldId,
  describedBy: describedBy.value,
  labelledBy: props.inline ? undefined : labelId,
  invalid: invalid.value,
}))
</script>
