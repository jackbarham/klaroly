<template>
  <div class="space-y-8">
    <div class="space-y-4">
      <p class="text-xs text-text-muted">
        Four combinations, and only two of them can be drawn standing still.
        Tab into the group below: the focused card takes a ring outside its
        edge, and the arrow keys move between them the way they do in any radio
        set. A card can be focused and selected at once, and it still reads as
        both, which is why focus is a ring here rather than the recoloured edge
        every other control uses. Its edge is already saying something.
      </p>
      <div class="grid gap-4 sm:grid-cols-3">
        <RadioCard
          v-for="rule in rules"
          :key="rule.value"
          v-model="chosen"
          name="kitchen-sink-travel"
          :value="rule.value"
          :title="rule.title"
          :description="rule.description"
        />
      </div>
      <p class="text-xs text-text-muted">
        Chosen: {{ chosen }}
      </p>
    </div>

    <div class="space-y-2">
      <p class="text-xs font-medium text-text-muted">
        Disabled
      </p>
      <div class="sm:max-w-form">
        <RadioCard
          v-model="unavailable"
          name="kitchen-sink-unavailable"
          value="later"
          title="Charge by the hour"
          description="Not available until the rate card is built."
          disabled
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// A bordered, selectable card for a small set of choices that are worth a
// sentence each. Selected is an accent border plus an accent ring, which is a
// two pixel edge that costs no layout.
import { ref } from 'vue'
import RadioCard from '@/components/form/RadioCard.vue'

const rules = [
  { value: 'included', title: 'Included', description: 'No separate charge for travel.' },
  { value: 'per_mile', title: 'A rate for every mile', description: 'Counted from your base postcode.' },
  { value: 'after_free_miles', title: 'Free, then a rate', description: 'The first few miles are on you.' },
]

const chosen = ref('per_mile')
const unavailable = ref('')
</script>
