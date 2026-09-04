<template>
  <button
    :class="[baseClasses, sizeClasses[size], variantClasses[variant]]"
    :type="type"
    :disabled="disabled"
    @click="emit('click')"
  >
    <Icon
      v-if="icon"
      :name="icon"
      class="h-5 w-5"
    />
    <slot />
  </button>
</template>

<script setup lang="ts">
// The only button component in the app. Anything that looks like a button is
// this with a different variant or size, so there is one place to change how
// a button behaves and one place to keep the focus ring honest.
import Icon, { type IconName } from '@/components/ui/Icon.vue'

withDefaults(defineProps<{
  variant?: 'primary' | 'secondary' | 'ghost'
  size?: 'small' | 'medium'
  icon?: IconName
  type?: 'button' | 'submit'
  disabled?: boolean
}>(), {
  variant: 'primary',
  size: 'medium',
  icon: undefined,
  type: 'button',
  disabled: false,
})

const emit = defineEmits<{
  click: []
}>()

// The gap is the one place a four pixel step is allowed: it sits between an
// icon and its label inside a single control.
const baseClasses = 'inline-flex items-center justify-center gap-1 rounded-control font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-neutral-900 disabled:cursor-not-allowed disabled:opacity-50'

const sizeClasses = {
  small: 'h-10 px-4 text-sm',
  medium: 'h-12 px-6',
}

const variantClasses = {
  primary: 'bg-neutral-900 text-neutral-0 hover:bg-neutral-800',
  secondary: 'border border-neutral-300 bg-neutral-0 text-neutral-900 hover:bg-neutral-50',
  ghost: 'text-neutral-700 hover:bg-neutral-100',
}
</script>
