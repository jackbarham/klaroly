<template>
  <!--
    The scroll margin keeps a heading clear of the sticky index when it is
    jumped to from one of its links.
  -->
  <section
    :id="id"
    class="scroll-mt-24"
  >
    <h2 class="text-lg font-semibold text-text-strong">
      {{ title }}
    </h2>
    <p
      v-if="note"
      class="mt-2 text-sm text-text-muted"
    >
      {{ note }}
    </p>
    <div class="mt-6 space-y-8">
      <slot />
    </div>
  </section>
</template>

<script setup lang="ts">
// One section of the kitchen sink: an anchor, a heading, an optional line of
// explanation and whatever is being shown. It exists so that the view reads
// as a list of sections rather than as one enormous template.
//
// It is deliberately plain. Nothing on this page may introduce an appearance
// of its own, because the point of the page is to show the system unchanged.
withDefaults(defineProps<{
  id: string
  title: string
  note?: string
}>(), {
  note: undefined,
})
</script>
