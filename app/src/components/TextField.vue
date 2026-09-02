<template>
  <div class="space-y-1">
    <label
      class="block text-sm text-ink-muted"
      :for="id"
    >{{ label }}</label>
    <input
      :id="id"
      v-model="model"
      class="w-full rounded-control border bg-surface-raised px-3 py-2 read-only:text-ink-muted"
      :class="error ? 'border-danger' : 'border-line'"
      :type="type"
      :autocomplete="autocomplete"
      :readonly="readonly"
      :aria-invalid="error ? 'true' : undefined"
      :aria-describedby="describedBy"
      @input="emit('input')"
    >
    <p
      v-if="hint"
      :id="`${id}-hint`"
      class="text-sm text-ink-muted"
    >
      {{ hint }}
    </p>
    <p
      v-if="error"
      :id="`${id}-error`"
      class="text-sm text-danger"
    >
      {{ error }}
    </p>
  </div>
</template>

<script setup lang="ts">
// A labelled text input with an optional hint and error. The error is wired
// to the input through aria-invalid and aria-describedby, and the hint is
// announced too when present.
import { computed } from 'vue'

const props = withDefaults(defineProps<{
  id: string
  label: string
  type?: string
  autocomplete?: string
  hint?: string
  error?: string
  readonly?: boolean
}>(), {
  type: 'text',
  autocomplete: undefined,
  hint: undefined,
  error: undefined,
  readonly: false,
})

const emit = defineEmits<{
  input: []
}>()

const model = defineModel<string>({ required: true })

const describedBy = computed(() => {
  const ids: string[] = []

  if (props.hint) {
    ids.push(`${props.id}-hint`)
  }

  if (props.error) {
    ids.push(`${props.id}-error`)
  }

  return ids.length > 0 ? ids.join(' ') : undefined
})
</script>
