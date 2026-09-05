<template>
  <select
    :id="id"
    v-model="model"
    class="h-12"
    :class="[controlClasses, edgeClasses(invalid)]"
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
import { controlClasses, edgeClasses, type ChoiceOption, type ControlProps } from '@/components/form/field'

defineProps<ControlProps & {
  options: ChoiceOption[]
}>()

const model = defineModel<string>({ required: true })
</script>
