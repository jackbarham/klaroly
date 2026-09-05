<template>
  <span
    class="inline-flex items-center gap-1 rounded-control px-2.5 py-0.5 text-meta font-medium whitespace-nowrap"
    :class="toneClasses[tone]"
  >
    <slot />
  </span>
</template>

<script lang="ts">
// The five things a pill can mean. A tone is not a state: a booking is
// Confirmed or Provisional and an enquiry is New or Quoted, and which of those
// reads as success and which as neutral is the screen's decision, not this
// component's. Nothing about bookings or enquiries appears in here.
export type PillTone = 'success' | 'warning' | 'danger' | 'info' | 'neutral'

// The fill is the status subtle token and the words are the status text token.
// That pairing is what survives the theme flip: in dark the fill becomes a
// white alpha and the text stays the full-strength hue, so the pill still
// reads at any depth.
//
// Neutral is Klaroly's own, added because a stage such as New or In
// conversation is not a success, a warning or a failure, and colouring it as
// one of the three would be saying something untrue.
const toneClasses: Record<PillTone, string> = {
  success: 'bg-success-subtle text-success-text',
  warning: 'bg-warning-subtle text-warning-text',
  danger: 'bg-danger-subtle text-danger-text',
  info: 'bg-info-subtle text-info-text',
  neutral: 'bg-surface-sunken text-text-muted',
}
</script>

<script setup lang="ts">
// A small word about the state of something. One step down from body size and
// medium weight to hold its own there.
withDefaults(defineProps<{
  tone?: PillTone
}>(), {
  tone: 'neutral',
})
</script>
