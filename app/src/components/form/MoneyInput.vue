<template>
  <!--
    The same shape as the text input: the input itself is the control and
    carries the shared classes, and the currency symbol is drawn inside its
    left edge. The wrapper exists only to position that symbol.
  -->
  <div class="relative">
    <input
      :id="id"
      v-model="model"
      class="h-12 pl-8"
      :class="[controlClasses, edgeClasses(invalid)]"
      type="text"
      inputmode="decimal"
      :disabled="disabled"
      :aria-labelledby="labelledBy"
      :aria-describedby="describedBy"
      :aria-invalid="invalid ? 'true' : undefined"
    >

    <span
      class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-text-muted"
      aria-hidden="true"
    >{{ currency }}</span>
  </div>
</template>

<script setup lang="ts">
// An amount of money, with the currency symbol shown in front of it. It
// formats nothing and parses nothing yet: the API stores money as an integer
// number of minor units, and turning what someone types into that is a job
// for the prompt that first saves a price.
//
// The symbol is decorative, so it is hidden from a screen reader and takes no
// clicks; the field's label says which currency it is.
import { controlClasses, edgeClasses, type ControlProps } from '@/components/form/field'

defineProps<ControlProps & {
  currency: string
}>()

const model = defineModel<string>({ required: true })
</script>
