<template>
  <textarea
    :id="id"
    v-model="model"
    class="min-h-32 py-2"
    :class="[controlClasses, edgeClasses(invalid)]"
    :rows="rows"
    :disabled="disabled"
    :aria-labelledby="labelledBy"
    :aria-describedby="describedBy"
    :aria-invalid="invalid ? 'true' : undefined"
  />
</template>

<script setup lang="ts">
// Several lines of text. Same wiring as the text input.
import { controlClasses, edgeClasses } from '@/components/form/field'

withDefaults(defineProps<{
  id: string
  rows?: number
  labelledBy?: string
  describedBy?: string
  invalid?: boolean
  disabled?: boolean
}>(), {
  rows: 4,
  labelledBy: undefined,
  describedBy: undefined,
  invalid: false,
  disabled: false,
})

const model = defineModel<string>({ required: true })
</script>
