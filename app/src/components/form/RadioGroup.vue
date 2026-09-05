<template>
  <div
    :id="id"
    class="space-y-2"
    role="radiogroup"
    :aria-labelledby="labelledBy"
    :aria-describedby="describedBy"
    :aria-invalid="invalid ? 'true' : undefined"
  >
    <label
      v-for="option in options"
      :key="option.value"
      :class="optionRowClasses"
    >
      <input
        v-model="model"
        class="check radio focus-visible:focus-ring"
        type="radio"
        :name="id"
        :value="option.value"
        :disabled="disabled"
      >
      <span>{{ option.label }}</span>
    </label>
  </div>
</template>

<script setup lang="ts">
// One of a short list, all of them visible. A group cannot be labelled by a
// label element's "for", so FormField hands over the id of its label and the
// group points at it with aria-labelledby.
import { optionRowClasses, type ChoiceOption, type ControlProps } from '@/components/form/field'

defineProps<ControlProps & {
  options: ChoiceOption[]
}>()

const model = defineModel<string>({ required: true })
</script>
