<template>
  <section class="space-y-6">
    <div class="space-y-2">
      <h2 class="text-lg font-semibold text-text-strong">
        {{ title }}
      </h2>
      <p
        v-if="description"
        class="text-sm text-text-muted"
      >
        {{ description }}
      </p>
    </div>
    <div class="space-y-6">
      <slot />
    </div>
  </section>
</template>

<script setup lang="ts">
// A titled group of fields. A long form is a handful of these rather than
// one list, so that a heading tells someone where they are.
defineProps<{
  title: string
  description?: string
}>()
</script>
