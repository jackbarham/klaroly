<template>
  <button
    :class="[baseClasses, sizeClasses[size], variantClasses[variant], dimmed ? 'opacity-50' : '']"
    :type="type"
    :disabled="disabled || pending"
    :aria-busy="pending ? 'true' : undefined"
  >
    <!--
      What the button says stays where it is and turns invisible, and the
      spinner is laid over the top of it, so a button does not change width or
      position halfway through a submit. The label is still in the page, so a
      screen reader still has it, and aria-busy says what is happening.
    -->
    <span
      class="inline-flex items-center gap-1"
      :class="pending ? 'opacity-0' : ''"
    >
      <Icon
        v-if="icon"
        :name="icon"
        class="h-5 w-5"
      />
      <slot />
    </span>

    <!--
      A ring with one side missing: a circle with a border on every side and a
      transparent top, turned by animate-spin. There is no icon for it, because
      a shape that has to move is not a path in Icon.vue.
    -->
    <span
      v-if="pending"
      class="absolute inset-0 flex items-center justify-center"
      aria-hidden="true"
    >
      <span class="h-5 w-5 animate-spin rounded-full border-2 border-current border-t-transparent motion-reduce:animate-none" />
    </span>
  </button>
</template>

<script setup lang="ts">
// The only button component in the app. Anything that looks like a button is
// this with a different variant or size, so there is one place to change how
// a button behaves and one place to keep the focus ring honest.
//
// The primary variant is the accent, and it is the only place in the app a
// filled accent is applied. A button never knows which screen it is on: a
// form's submit, the sidebar's New button and a settings page's Save are all
// the primary action, so they are all the same button.
//
// A click is the native event on the root element, which Vue passes through:
// the component declares no events of its own.
import { computed } from 'vue'
import Icon, { type IconName } from '@/components/ui/Icon.vue'

// Pending means a request this button started is still in flight. It
// disables the button, so a form cannot be submitted twice, and says so with
// aria-busy, because a disabled button on its own does not explain itself.
const props = withDefaults(defineProps<{
  variant?: 'primary' | 'secondary' | 'ghost'
  size?: 'small' | 'medium'
  icon?: IconName
  type?: 'button' | 'submit'
  disabled?: boolean
  pending?: boolean
}>(), {
  variant: 'primary',
  size: 'medium',
  type: 'button',
})

// Half opacity is how the app says unavailable, and a button doing what it
// was asked to do is not unavailable. So the dimming belongs to disabled
// alone, and it is worked out from the props rather than from the disabled
// attribute, which pending sets as well.
const dimmed = computed(() => props.disabled && !props.pending)

// Position is relative so the spinner can be laid over the label. The gap
// between an icon and its label sits on the label wrapper in the template: it
// is the one place a four pixel step is allowed, inside a single control.
const baseClasses = 'relative inline-flex items-center justify-center rounded-control font-medium transition-colors focus-visible:focus-ring disabled:cursor-not-allowed'

const sizeClasses = {
  small: 'h-10 px-4 text-sm',
  medium: 'h-12 px-6',
}

const variantClasses = {
  primary: 'bg-accent text-text-on-accent hover:bg-accent-hover',
  secondary: 'border border-border-strong bg-surface text-text-strong hover:bg-surface-sunken',
  ghost: 'text-text hover:bg-surface-sunken',
}
</script>
