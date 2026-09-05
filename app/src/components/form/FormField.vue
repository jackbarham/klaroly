<template>
  <div class="space-y-2">
    <!--
      The label carries "for" so that clicking it focuses a real input. A
      control that is not a labelable element, such as the toggle switch or a
      radio group, ignores "for" and points at the label's id with
      aria-labelledby instead, which is why the id is handed to the slot too.
    -->
    <label
      :id="labelId"
      class="block text-sm font-medium text-text"
      :for="fieldId"
    >{{ label }}</label>

    <slot
      :id="fieldId"
      :described-by="describedBy"
      :labelled-by="labelId"
      :invalid="invalid"
    />

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
// statusMessage is the words for a control that shows a live check as
// someone types, which TextInput draws as a tick or a cross. The mark is
// inside the control's box, so the control draws it; the words belong out
// here with the hint and the error, so the field says them.
import { computed, useId } from 'vue'

const props = defineProps<{
  label: string
  hint?: string
  error?: string
  statusMessage?: string
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
</script>
