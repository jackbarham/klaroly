<template>
  <button
    :id="id"
    class="inline-flex h-8 w-14 shrink-0 items-center rounded-full border p-1 transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-border-focus disabled:cursor-not-allowed disabled:opacity-50"
    :class="model ? 'justify-end border-accent bg-accent' : 'justify-start border-border-strong bg-border-strong'"
    type="button"
    role="switch"
    :aria-checked="model"
    :aria-labelledby="labelledBy"
    :aria-describedby="describedBy"
    :aria-invalid="invalid ? 'true' : undefined"
    :disabled="disabled"
    @click="toggle"
  >
    <span
      class="h-6 w-6 rounded-full bg-text-on-accent"
      aria-hidden="true"
    />
  </button>
</template>

<script setup lang="ts">
// On or off. The knob's position says which, so the state does not depend on
// telling two greys apart, and aria-checked says it out loud. A button is
// not a labelable element, so it takes the id of FormField's label rather
// than relying on that label's "for".
//
// It takes the same four props as every other control. Its border is already
// carrying on and off, so an invalid switch says so through aria-invalid and
// the field's error message rather than through a second border treatment.
withDefaults(defineProps<{
  id: string
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

const model = defineModel<boolean>({ required: true })

function toggle(): void {
  model.value = !model.value
}
</script>
