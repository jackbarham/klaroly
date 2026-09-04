<template>
  <input
    :id="id"
    v-model="model"
    class="h-12"
    :class="[controlClasses, borderClasses(invalid)]"
    :type="type"
    :autocomplete="autocomplete"
    :disabled="disabled"
    :aria-labelledby="labelledBy"
    :aria-describedby="describedBy"
    :aria-invalid="invalid ? 'true' : undefined"
  >
</template>

<script setup lang="ts">
// A single line of text. The id, the aria-describedby and the invalid flag
// come from FormField, which owns the label, the hint and the error.
import { borderClasses, controlClasses } from '@/components/form/field'

withDefaults(defineProps<{
  id: string
  type?: 'text' | 'email' | 'tel' | 'url' | 'date'
  autocomplete?: string
  labelledBy?: string
  describedBy?: string
  invalid?: boolean
  disabled?: boolean
}>(), {
  type: 'text',
  autocomplete: undefined,
  labelledBy: undefined,
  describedBy: undefined,
  invalid: false,
  disabled: false,
})

const model = defineModel<string>({ required: true })
</script>
