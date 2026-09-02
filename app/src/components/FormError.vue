<template>
  <p
    v-if="message"
    class="rounded-control border border-danger px-3 py-2 text-sm text-danger"
    role="alert"
  >
    {{ message }}
  </p>
</template>

<script setup lang="ts">
// A form-level error line for failures that belong to no single field, such
// as a rate limit or a lost connection.
withDefaults(defineProps<{
  message?: string | null
}>(), {
  message: null,
})
</script>
