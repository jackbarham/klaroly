<template>
  <button
    :class="[baseClasses, sizeClasses[size], variantClasses[variant]]"
    :type="type"
    :disabled="disabled || pending"
    :aria-busy="pending ? 'true' : undefined"
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
//
// The primary variant is the brand colour, and it is the only place in the
// app that colour is applied. A button never knows which screen it is on: a
// form's submit, the sidebar's New button and a settings page's Save are all
// the primary action, so they are all the same button.
import Icon, { type IconName } from '@/components/ui/Icon.vue'

// Pending means a request this button started is still in flight. It
// disables the button, so a form cannot be submitted twice, and says so with
// aria-busy, because a disabled button on its own does not explain itself.
withDefaults(defineProps<{
  variant?: 'primary' | 'secondary' | 'ghost'
  size?: 'small' | 'medium'
  icon?: IconName
  type?: 'button' | 'submit'
  disabled?: boolean
  pending?: boolean
}>(), {
  variant: 'primary',
  size: 'medium',
  icon: undefined,
  type: 'button',
  disabled: false,
  pending: false,
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
  primary: 'bg-brand text-on-brand hover:bg-brand-strong',
  secondary: 'border border-neutral-300 bg-neutral-0 text-neutral-900 hover:bg-neutral-50',
  ghost: 'text-neutral-700 hover:bg-neutral-100',
}
</script>
