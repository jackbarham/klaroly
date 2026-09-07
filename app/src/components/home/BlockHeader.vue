<template>
  <div class="mb-1 flex items-baseline justify-between gap-4 px-6 @split:px-4">
    <h2
      :id="headingId"
      class="text-lg font-semibold text-text-strong"
    >
      {{ title }}
    </h2>

    <slot />
  </div>
</template>

<script setup lang="ts">
// The heading row every home block starts with: the title on the left and,
// on the right, whatever link the block offers. One component rather than
// three copies, so the three blocks cannot drift apart by a pixel.
//
// The link is a slot rather than props, because the attention block's is
// conditional and hidden above the split while the other two are always
// there, and a prop shape covering both would be a switch on which block this
// is. The heading id comes in from the block, which needs it for its own
// section's aria-labelledby and makes it with useId() like every other id in
// the kit, rather than three ids written by hand.
defineProps<{
  title: string
  headingId: string
}>()
</script>
