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
import { controlClasses, edgeClasses, type ControlProps } from '@/components/form/field'

withDefaults(defineProps<ControlProps & {
  rows?: number
}>(), {
  rows: 4,
})

const model = defineModel<string>({ required: true })
</script>
