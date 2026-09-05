<template>
  <TextInput
    :id="id"
    v-model="model"
    type="date"
    :labelled-by="labelledBy"
    :described-by="describedBy"
    :invalid="invalid"
    :disabled="disabled"
  />
</template>

<script setup lang="ts">
// A date. It is the text input with the browser's own date control turned
// on, and nothing else: two components that differ only by a prop are one
// component, so this is a named wrapper rather than a second implementation.
import TextInput from '@/components/form/TextInput.vue'
import type { ControlProps } from '@/components/form/field'

defineProps<ControlProps>()

const model = defineModel<string>({ required: true })
</script>
