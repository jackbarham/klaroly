<template>
  <label class="block cursor-pointer">
    <!--
      A real radio, kept out of sight but not out of the page. Several cards
      sharing a name are one arrow-key group, exactly as a plain radio set is,
      and the card is the label that names it.
    -->
    <input
      v-model="model"
      class="peer sr-only"
      type="radio"
      :name="name"
      :value="value"
      :disabled="disabled"
    >

    <!--
      The focus ring is an exception to the app's rule that a control with a
      visible edge recolours that edge. Here the edge is already saying whether
      the card is selected, so recolouring it on focus would make a focused
      card that is not selected look exactly like a selected one. The ring sits
      outside the edge instead, so the three states stay distinguishable.
    -->
    <span
      class="block rounded-card border p-4 transition-colors duration-200 peer-focus-visible:focus-ring peer-disabled:cursor-not-allowed peer-disabled:opacity-50"
      :class="selected ? 'border-accent ring-1 ring-accent' : 'border-border-strong'"
    >
      <span class="block text-body font-medium text-text-strong">{{ title }}</span>
      <span
        v-if="description"
        class="mt-1 block text-body text-text-muted"
      >{{ description }}</span>
    </span>
  </label>
</template>

<script setup lang="ts">
// One of a small set of choices that are worth explaining, where a plain radio
// and a line of text would not give the description anywhere to live.
//
// Selected is an accent border plus an accent ring. Both are one pixel, and a
// ring is drawn outside the box rather than in the layout, so the card reads
// as a two pixel edge and nothing moves when it is chosen.
import { computed } from 'vue'

const props = defineProps<{
  // Shared by every card in the group, which is what makes them one group.
  name: string
  value: string
  title: string
  description?: string
  disabled?: boolean
}>()

const model = defineModel<string>({ required: true })

const selected = computed(() => model.value === props.value)
</script>
