<template>
  <button
    class="w-full rounded-control bg-brand px-4 py-2 font-medium text-on-brand hover:bg-brand-strong disabled:opacity-60"
    type="submit"
    :disabled="pending"
    :aria-busy="pending ? 'true' : undefined"
  >
    <slot />
  </button>
</template>

<script setup lang="ts">
// Disabled while the form's request is in flight, so a form submits once.
defineProps<{
  pending: boolean
}>()
</script>
