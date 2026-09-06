<template>
  <span
    class="inline-flex items-center gap-1 rounded-control px-2.5 py-0.5 text-meta font-medium whitespace-nowrap"
    :class="toneClasses[tone]"
  >
    <slot />
  </span>
</template>

<script lang="ts">
// The six things a pill can mean. A tone is not a state: a booking is
// Confirmed or Provisional and an enquiry is New or Quoted, and which of those
// reads as success and which as neutral is the screen's decision, not this
// component's. Nothing about bookings or enquiries appears in here.
export type PillTone = 'success' | 'warning' | 'danger' | 'info' | 'neutral' | 'accent'

// The fill is the status subtle token and the words are the status text token.
// That pairing is what survives the theme flip: in dark the fill becomes a
// white alpha and the text stays the full-strength hue, so the pill still
// reads at any depth.
//
// Neutral is Klaroly's own, added because a stage such as New or In
// conversation is not a success, a warning or a failure, and colouring it as
// one of the three would be saying something untrue.
//
// Accent is Klaroly's too, and it is the one tone that is not a status. It
// says "this is the thing about this row", which is what Contacts needs for
// Upcoming: a client with work booked is not a success or a warning, she is
// the one you would look at first. It follows the same subtle-fill,
// text-colour pairing as the other five, so it survives the theme flip the
// same way, and using it is still a screen's decision.
const toneClasses: Record<PillTone, string> = {
  success: 'bg-success-subtle text-success-text',
  warning: 'bg-warning-subtle text-warning-text',
  danger: 'bg-danger-subtle text-danger-text',
  info: 'bg-info-subtle text-info-text',
  neutral: 'bg-surface-sunken text-text-muted',
  accent: 'bg-accent-subtle text-accent-text',
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
