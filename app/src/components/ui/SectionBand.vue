<template>
  <section class="space-y-4">
    <!--
      A sunken bar, not a bordered box with a header inside it. This is what a
      section of a page gets instead of a nested panel, and it is half of why
      the fewer-boxes rule is liveable.
    -->
    <component
      :is="collapsible ? 'button' : 'div'"
      class="flex h-12 w-full items-center justify-between rounded-control bg-surface-sunken px-4 text-left"
      :class="collapsible ? 'cursor-pointer focus-visible:focus-ring' : ''"
      :type="collapsible ? 'button' : undefined"
      :aria-expanded="collapsible ? open : undefined"
      :aria-controls="collapsible ? contentId : undefined"
      @click="toggle"
    >
      <span class="text-body font-medium text-text-strong">{{ title }}</span>
      <Icon
        v-if="collapsible"
        name="chevron-right"
        class="h-5 w-5 shrink-0 text-text-muted transition-transform"
        :class="open ? 'rotate-90' : ''"
      />
    </component>

    <div
      v-if="open"
      :id="contentId"
    >
      <slot />
    </div>
  </section>
</template>

<script setup lang="ts">
// The heading of a section, and what is under it.
//
// When it collapses the bar is a real button, so it takes a focus ring like
// every other button: there is no edge of its own to recolour. It owns whether
// it is open, because nothing outside it has any reason to know.
import { ref, useId } from 'vue'
import Icon from '@/components/ui/Icon.vue'

const props = defineProps<{
  title: string
  collapsible?: boolean
}>()

// A band that does not collapse is always open, so the content is never hidden
// by a state nobody can change.
const open = ref(true)

const contentId = useId()

function toggle(): void {
  if (!props.collapsible) {
    return
  }

  open.value = !open.value
}
</script>
