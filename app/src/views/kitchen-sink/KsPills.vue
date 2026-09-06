<template>
  <div class="space-y-8">
    <div class="space-y-4">
      <h3 class="text-sm font-medium text-text">
        The six tones
      </h3>
      <div class="flex flex-wrap items-center gap-4">
        <StatusPill
          v-for="tone in tones"
          :key="tone"
          :tone="tone"
        >
          {{ tone }}
        </StatusPill>
      </div>
      <p class="text-xs text-text-muted">
        Neutral is Klaroly's, added because a stage such as New is not a
        success, a warning or a failure. Accent is Klaroly's too, and it is the
        one tone that is not a status: it says "this is the thing about this
        row", which is what Contacts uses for Upcoming. The other four are the
        guide's.
      </p>
    </div>

    <div class="space-y-4">
      <h3 class="text-sm font-medium text-text">
        What a tone is not
      </h3>
      <p class="text-xs text-text-muted">
        The pill knows nothing about bookings or enquiries. The mapping below is
        an illustration of what a screen might choose, not a decision this
        component makes, and the screens that need it are still to be built.
      </p>
      <div class="grid gap-6 sm:grid-cols-2">
        <div class="space-y-2">
          <p class="text-xs font-medium text-text-muted">
            A booking mark
          </p>
          <div class="flex flex-wrap items-center gap-2">
            <StatusPill
              v-for="mark in bookingMarks"
              :key="mark.label"
              :tone="mark.tone"
            >
              {{ mark.label }}
            </StatusPill>
          </div>
        </div>
        <div class="space-y-2">
          <p class="text-xs font-medium text-text-muted">
            An enquiry stage
          </p>
          <div class="flex flex-wrap items-center gap-2">
            <StatusPill
              v-for="stage in enquiryStages"
              :key="stage.label"
              :tone="stage.tone"
            >
              {{ stage.label }}
            </StatusPill>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// The pill on its own. It is also shown in a list row and in a table row in
// the next section, because a pill by itself says nothing about whether it
// works where it will actually live.
import type { PillTone } from '@/components/ui/StatusPill.vue'

const tones: PillTone[] = ['neutral', 'info', 'success', 'warning', 'danger', 'accent']

interface Mark {
  label: string
  tone: PillTone
}

const bookingMarks: Mark[] = [
  { label: 'Confirmed', tone: 'success' },
  { label: 'Provisional', tone: 'warning' },
  { label: 'Possible', tone: 'neutral' },
]

const enquiryStages: Mark[] = [
  { label: 'New', tone: 'neutral' },
  { label: 'In conversation', tone: 'info' },
  { label: 'Possible', tone: 'neutral' },
  { label: 'Quoted', tone: 'info' },
  { label: 'Booked', tone: 'success' },
  { label: 'Lost', tone: 'danger' },
]
</script>
