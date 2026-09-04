<template>
  <label
    class="flex items-start gap-2 text-neutral-900"
    :for="id"
  >
    <input
      :id="id"
      v-model="model"
      class="mt-1 h-5 w-5 shrink-0 accent-neutral-900"
      type="checkbox"
      :disabled="disabled"
      :aria-labelledby="labelledBy"
      :aria-describedby="describedBy"
      :aria-invalid="invalid ? 'true' : undefined"
    >
    <span>{{ label }}</span>
  </label>
</template>

<script setup lang="ts">
// A single checkbox. Unlike the other controls this one carries its own
// label, because a tick box reads as the sentence beside it rather than as a
// field with a heading, so it is normally used outside a FormField. It still
// takes the same four props as every other control, so that v-bind on
// FormField's slot works when a checkbox does belong in a field.
withDefaults(defineProps<{
  id: string
  label: string
  labelledBy?: string
  describedBy?: string
  invalid?: boolean
  disabled?: boolean
}>(), {
  labelledBy: undefined,
  describedBy: undefined,
  invalid: false,
  disabled: false,
})

const model = defineModel<boolean>({ required: true })
</script>
