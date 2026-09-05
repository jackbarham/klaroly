<template>
  <button
    :id="id"
    class="relative inline-flex h-7 w-12 shrink-0 items-center rounded-full p-1 transition-colors before:absolute before:inset-x-0 before:-inset-y-2 focus-visible:focus-ring disabled:cursor-not-allowed disabled:opacity-50"
    :class="model ? 'bg-accent' : 'bg-border-strong'"
    type="button"
    role="switch"
    :aria-checked="model"
    :aria-labelledby="labelledBy"
    :aria-describedby="describedBy"
    :aria-invalid="invalid ? 'true' : undefined"
    :disabled="disabled"
    @click="model = !model"
  >
    <span
      class="h-5 w-5 rounded-full bg-text-on-accent transition-transform motion-reduce:transition-none"
      :class="model ? 'translate-x-5' : 'translate-x-0'"
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
// The knob moves with a transform rather than with justify-start and
// justify-end. Both put it in the same two places, but a layout change is
// instant and a transform can be watched, and watching it travel is what
// says the switch was thrown rather than redrawn.
//
// The track is 28px tall, which is under the 44px minimum, so the pseudo
// element stretches the hit area to 44px without changing anything you can
// see. That is the same trick the style guide uses, and it is why the track
// can be this size at all.
//
// The track is already carrying on and off in its fill, so an invalid switch
// says so through aria-invalid and the field's error message rather than
// through a second treatment.
import type { ControlProps } from '@/components/form/field'

defineProps<ControlProps>()

const model = defineModel<boolean>({ required: true })
</script>
