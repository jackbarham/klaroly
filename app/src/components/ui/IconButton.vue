<template>
  <button
    class="inline-flex h-12 w-12 items-center justify-center rounded-control text-neutral-700 transition-colors hover:bg-neutral-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-neutral-900 disabled:cursor-not-allowed disabled:opacity-50"
    :type="type"
    :disabled="disabled"
    :aria-label="label"
    @click="emit('click')"
  >
    <Icon
      :name="icon"
      class="h-5 w-5"
    />
  </button>
</template>

<script setup lang="ts">
// A square button with an icon and no visible text. The label is required
// and becomes the accessible name, because an icon on its own says nothing
// to a screen reader.
import Icon, { type IconName } from '@/components/ui/Icon.vue'

withDefaults(defineProps<{
  icon: IconName
  label: string
  type?: 'button' | 'submit'
  disabled?: boolean
}>(), {
  type: 'button',
  disabled: false,
})

const emit = defineEmits<{
  click: []
}>()
</script>
