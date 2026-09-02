<template>
  <main class="flex min-h-screen items-center justify-center p-6">
    <div class="w-full max-w-sm space-y-6 rounded-card border border-line bg-surface-raised p-8">
      <h1 class="text-2xl font-semibold">
        {{ title }}
      </h1>
      <slot />
    </div>
  </main>
</template>

<script setup lang="ts">
// The centred card every authentication screen sits in. One column at every
// width (decision 10); the card simply narrows on a phone.
defineProps<{
  title: string
}>()
</script>
