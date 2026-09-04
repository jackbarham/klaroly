<template>
  <!--
    The wrapper exists so that the status mark can be positioned inside the
    right edge of the field. Without a status there is nothing in it but the
    input, which fills it.
  -->
  <div class="relative">
    <input
      :id="id"
      v-model="model"
      class="h-12"
      :class="[controlClasses, borderClasses(invalid), status ? 'pr-12' : '']"
      :type="type"
      :autocomplete="autocomplete"
      :disabled="disabled"
      :aria-labelledby="labelledBy"
      :aria-describedby="describedBy"
      :aria-invalid="invalid ? 'true' : undefined"
    >

    <span
      v-if="status"
      class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-neutral-900"
      aria-hidden="true"
    >
      <svg
        class="h-5 w-5"
        viewBox="0 0 20 20"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
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
</template>

<script setup lang="ts">
// A single line of text. The id, the aria-describedby and the invalid flag
// come from FormField, which owns the label, the hint and the error.
//
// The optional status draws a tick or a cross inside the right edge, for a
// check that happens while someone types rather than when they submit. It is
// a shape, not a colour, and it is hidden from a screen reader: the same
// thing in words is FormField's statusMessage, and a status passed without
// one is a tick nobody can hear. Pass the pair or pass neither.
import { borderClasses, controlClasses } from '@/components/form/field'

withDefaults(defineProps<{
  id: string
  type?: 'text' | 'email' | 'password' | 'tel' | 'url' | 'date'
  autocomplete?: string
  labelledBy?: string
  describedBy?: string
  invalid?: boolean
  disabled?: boolean
  status?: 'valid' | 'invalid'
}>(), {
  type: 'text',
  autocomplete: undefined,
  labelledBy: undefined,
  describedBy: undefined,
  invalid: false,
  disabled: false,
  status: undefined,
})

const model = defineModel<string>({ required: true })
</script>
