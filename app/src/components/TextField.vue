<template>
  <div class="space-y-1">
    <label
      class="block text-sm text-ink-muted"
      :for="id"
    >{{ label }}</label>
    <div class="relative">
      <input
        :id="id"
        v-model="model"
        class="w-full rounded-control border bg-surface-raised px-3 py-2 read-only:text-ink-muted"
        :class="[
          error ? 'border-danger' : 'border-line',
          status ? 'pr-10' : '',
        ]"
        :type="type"
        :autocomplete="autocomplete"
        :readonly="readonly"
        :aria-invalid="error ? 'true' : undefined"
        :aria-describedby="describedBy"
        @input="emit('input')"
      >
      <span
        v-if="status"
        class="pointer-events-none absolute inset-y-0 right-3 flex items-center"
        :class="status === 'valid' ? 'text-success' : 'text-danger'"
      >
        <svg
          class="h-5 w-5"
          viewBox="0 0 20 20"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
          aria-hidden="true"
        >
          <polyline
            v-if="status === 'valid'"
            points="4,10.5 8,14.5 16,6"
          />
          <g v-else>
            <line
              x1="5"
              y1="5"
              x2="15"
              y2="15"
            />
            <line
              x1="15"
              y1="5"
              x2="5"
              y2="15"
            />
          </g>
        </svg>
      </span>
    </div>
    <p
      v-if="hint"
      :id="`${id}-hint`"
      class="text-sm"
      :class="status === 'invalid' ? 'text-danger' : 'text-ink-muted'"
    >
      {{ hint }}
    </p>
    <p
      v-if="statusMessage"
      class="sr-only"
      role="status"
    >
      {{ statusMessage }}
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
//
// The optional status shows a tick or a cross inside the right edge of the
// field, and turns the hint red when it is invalid. A tick and a cross are
// colour and shape only, which a screen reader cannot read, so callers pass
// statusMessage as well and that is announced instead.
import { computed } from 'vue'

const props = withDefaults(defineProps<{
  id: string
  label: string
  type?: string
  autocomplete?: string
  hint?: string
  error?: string
  readonly?: boolean
  status?: 'valid' | 'invalid'
  statusMessage?: string
}>(), {
  type: 'text',
  autocomplete: undefined,
  hint: undefined,
  error: undefined,
  readonly: false,
  status: undefined,
  statusMessage: undefined,
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
