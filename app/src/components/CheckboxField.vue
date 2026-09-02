<template>
  <label
    class="flex items-start gap-2 text-sm"
    :for="id"
  >
    <input
      :id="id"
      v-model="model"
      class="mt-1 accent-brand"
      type="checkbox"
    >
    <span>{{ label }}</span>
  </label>
</template>

<script setup lang="ts">
defineProps<{
  id: string
  label: string
}>()

const model = defineModel<boolean>({ required: true })
</script>
