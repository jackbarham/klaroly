<template>
  <div
    class="flex items-center rounded-control border bg-neutral-0"
    :class="borderClasses(invalid)"
  >
    <span
      class="pl-4 text-neutral-500"
      aria-hidden="true"
    >{{ currency }}</span>
    <input
      :id="id"
      v-model="model"
      class="h-12 w-full rounded-control bg-neutral-0 px-2 text-neutral-900 placeholder:text-neutral-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-neutral-900 disabled:cursor-not-allowed disabled:bg-neutral-50 disabled:text-neutral-400"
      type="text"
      inputmode="decimal"
      :disabled="disabled"
      :aria-labelledby="labelledBy"
      :aria-describedby="describedBy"
      :aria-invalid="invalid ? 'true' : undefined"
    >
  </div>
</template>

<script setup lang="ts">
// An amount of money, with the currency symbol shown in front of it. It
// formats nothing and parses nothing yet: the API stores money as an integer
// number of minor units, and turning what someone types into that is a job
// for the prompt that first saves a price.
//
// The symbol is decorative, so it is hidden from a screen reader; the field's
// label says which currency it is.
import { borderClasses } from '@/components/form/field'

withDefaults(defineProps<{
  id: string
  currency: string
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
