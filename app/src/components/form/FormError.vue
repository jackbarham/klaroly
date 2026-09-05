<template>
  <p
    v-if="message"
    class="rounded-control border-2 border-danger px-4 py-2 text-sm font-medium text-danger-text"
    role="alert"
  >
    {{ message }}
  </p>
</template>

<script setup lang="ts">
// A form-level error for a failure that belongs to no single field, such as
// a rate limit or a lost connection. FormField handles the per-field kind;
// this is the rest.
//
// It borrows the invalid control's own treatment, a heavy border and a
// heavier weight, so that a form has one way of saying something is wrong
// rather than one for a field and another for the form.
defineProps<{
  message?: string | null
}>()
</script>
