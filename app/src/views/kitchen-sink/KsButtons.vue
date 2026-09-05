<template>
  <!--
    Every variant against every size and state, as a grid, so that a
    combination nobody has thought about is an obvious hole rather than a
    missing example. The grid keeps its width on a phone and scrolls
    sideways, because squeezing the columns would stop it comparing.
  -->
  <div class="overflow-x-auto">
    <div class="grid min-w-2xl grid-cols-4 items-center gap-4">
      <p class="text-xs font-medium text-text-muted">
        State
      </p>
      <p
        v-for="variant in variants"
        :key="variant"
        class="text-xs font-medium text-text-muted"
      >
        {{ variant }}
      </p>

      <template
        v-for="row in rows"
        :key="row.label"
      >
        <p class="text-xs text-text-muted">
          {{ row.label }}
        </p>
        <div
          v-for="variant in variants"
          :key="variant"
        >
          <AppButton
            :variant="variant"
            :size="row.size"
            :icon="row.icon"
            :icon-end="row.iconEnd"
            :disabled="row.disabled"
            :pending="row.pending"
          >
            Save
          </AppButton>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
// AppButton is the only button component in the app, so this grid is every
// button the app has.
import type { IconName } from '@/components/ui/Icon.vue'

interface ButtonRow {
  label: string
  size: 'small' | 'medium'
  icon?: IconName
  iconEnd?: IconName
  disabled: boolean
  pending: boolean
}

const variants = ['primary', 'secondary', 'ghost'] as const

const rows: ButtonRow[] = [
  { label: 'Small', size: 'small', icon: undefined, iconEnd: undefined, disabled: false, pending: false },
  { label: 'Medium', size: 'medium', icon: undefined, iconEnd: undefined, disabled: false, pending: false },
  { label: 'Small, icon', size: 'small', icon: 'plus', iconEnd: undefined, disabled: false, pending: false },
  { label: 'Medium, icon', size: 'medium', icon: 'plus', iconEnd: undefined, disabled: false, pending: false },
  { label: 'Trailing icon', size: 'medium', icon: undefined, iconEnd: 'chevron-right', disabled: false, pending: false },
  { label: 'Both icons', size: 'medium', icon: 'chevron-left', iconEnd: 'chevron-right', disabled: false, pending: false },
  // Disabled and pending sit next to each other on purpose: they used to be
  // the same picture, and the point of the pair is that they no longer are.
  { label: 'Disabled', size: 'medium', icon: undefined, iconEnd: undefined, disabled: true, pending: false },
  { label: 'Pending', size: 'medium', icon: undefined, iconEnd: undefined, disabled: false, pending: true },
  { label: 'Disabled, icon', size: 'medium', icon: 'plus', iconEnd: undefined, disabled: true, pending: false },
  { label: 'Pending, icon', size: 'medium', icon: 'plus', iconEnd: undefined, disabled: false, pending: true },
  { label: 'Pending, trailing icon', size: 'medium', icon: undefined, iconEnd: 'chevron-right', disabled: false, pending: true },
]
</script>
