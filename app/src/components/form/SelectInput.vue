<template>
  <!--
    The wrapper exists so that the chevron can sit inside the right edge of
    the control, in the same place and at the same size as a text input's
    status mark. appearance-none is what takes the browser's own arrow away,
    and the padding is what stops a long option running underneath ours.
  -->
  <div class="relative">
    <select
      :id="id"
      v-model="model"
      class="h-12 cursor-pointer appearance-none"
      :class="[controlClasses, edgeClasses(invalid), 'pr-12']"
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

    <span
      class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-text"
      aria-hidden="true"
    >
      <Icon
        class="h-5 w-5"
        name="chevron-down"
      />
    </span>
  </div>
</template>

<script setup lang="ts">
// One of a list. The browser's own select, so that a phone shows the picker
// its owner already knows how to use, with our own chevron on it: the
// platform draws a hairline arrow that is thinner than every other mark in
// the app, and it is the one part of a select that can be replaced without
// giving up the native picker.
import Icon from '@/components/ui/Icon.vue'
import { controlClasses, edgeClasses, type ChoiceOption, type ControlProps } from '@/components/form/field'

defineProps<ControlProps & {
  options: ChoiceOption[]
}>()

const model = defineModel<string>({ required: true })
</script>
