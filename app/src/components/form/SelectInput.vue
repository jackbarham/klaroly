<template>
  <select
    :id="id"
    v-model="model"
    class="h-12"
    :class="[controlClasses, borderClasses(invalid)]"
    :disabled="disabled"
    :aria-labelledby="labelledBy"
    :aria-describedby="describedBy"
    :aria-invalid="invalid ? 'true' : undefined"
  >
    <option
      v-for="option in options"
      :key="option.value"
      :value="option.value"
    >
      {{ option.label }}
    </option>
  </select>
</template>

<script setup lang="ts">
// One of a list. The browser's own select, so that a phone shows the picker
// its owner already knows how to use.
import { borderClasses, controlClasses } from '@/components/form/field'

// A script setup block cannot export, so callers pass a plain array of
// objects with these two keys and TypeScript matches it up by shape.
interface SelectOption {
  value: string
  label: string
}

withDefaults(defineProps<{
  id: string
  options: SelectOption[]
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

const model = defineModel<string>({ required: true })
</script>
