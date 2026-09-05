<template>
  <div class="flex flex-col items-center gap-4 py-12 text-center">
    <Icon
      :name="icon"
      class="h-8 w-8 text-text-subtle"
    />
    <p class="text-text-muted">
      {{ text }}
    </p>
    <div v-if="$slots.action">
      <slot name="action" />
    </div>
  </div>
</template>

<script setup lang="ts">
// What a panel shows when it has nothing in it: an icon, one line and, when
// there is something useful to do about it, an action.
import Icon, { type IconName } from '@/components/ui/Icon.vue'

withDefaults(defineProps<{
  text: string
  icon?: IconName
}>(), {
  icon: 'page',
})
</script>
