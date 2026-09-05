<template>
  <!--
    On its own the row is the control and carries its own words. Inside a
    FormField there are no words here to click, so the row is a plain wrapper
    and the field's label does that job.
  -->
  <component
    :is="label ? 'label' : 'div'"
    :class="label ? optionRowClasses : 'flex items-start gap-2'"
    :for="label ? id : undefined"
  >
    <input
      :id="id"
      v-model="model"
      class="h-5 w-5 shrink-0 accent-accent focus-visible:focus-ring"
      :class="label ? 'mt-1' : ''"
      type="checkbox"
      :disabled="disabled"
      :aria-labelledby="labelledBy"
      :aria-describedby="describedBy"
      :aria-invalid="invalid ? 'true' : undefined"
    >
    <span v-if="label">{{ label }}</span>
  </component>
</template>

<script setup lang="ts">
// A single checkbox, in one of two shapes, and never in both.
//
// On its own it carries its own label, because a tick box reads as the
// sentence beside it rather than as a field with a heading, and that is how
// the sign-in and register screens use it. Inside a FormField it carries no
// label of its own and is named by the field's, which is what labelledBy is.
// A control with two labels pointing at it is announced twice.
//
// Exactly one of label and labelledBy is required. That cannot be said in
// the prop types, because defineProps flattens a union into optional
// properties, so it is checked below instead, where it fails at once while
// the screen is being written. The branch is compiled out of a build.
import { optionRowClasses, type ControlProps } from '@/components/form/field'

const props = defineProps<ControlProps & {
  label?: string
}>()

if (import.meta.env.DEV && Boolean(props.label) === Boolean(props.labelledBy)) {
  throw new Error('CheckboxInput takes either a label of its own or the labelledBy of the field around it, and never both')
}

const model = defineModel<boolean>({ required: true })
</script>
