<template>
  <!--
    The screen an artist meets before they have put anything in, which is the
    one most likely to lose somebody. Every block is empty and the empty-block
    rule would leave a blank page, so this goes in place of all three rather
    than three empty states.

    **It does two things and deliberately not five.** It offers both ways in,
    because an artist arriving with a diary full of work wants the first and one
    arriving mid-conversation wants the second. And it names the sit-down job
    underneath, because the rate card is what makes every later quote a few taps
    instead of typing, and an artist who never finds it is still typing in
    October.

    What it does NOT do is start a tour, ask for a profile photo or show a
    checklist with ticks on it. Each delays the first useful thing, and the first
    useful thing is one real booking on the calendar.
  -->
  <div class="py-6 text-center">
    <p class="text-xl font-semibold text-text-strong">
      {{ t('home.first_run.title') }}
    </p>
    <p class="mx-auto mt-2 max-w-prose text-text-muted">
      {{ t('home.first_run.body') }}
    </p>

    <div class="mt-6 flex flex-wrap justify-center gap-3">
      <AppButton
        variant="primary"
        @click="emit('create')"
      >
        {{ t('home.first_run.add_booking') }}
      </AppButton>
      <AppButton
        variant="secondary"
        @click="emit('create')"
      >
        {{ t('home.first_run.add_enquiry') }}
      </AppButton>
    </div>

    <div class="mt-8 rounded-card border border-border bg-surface-sunken p-4 text-left">
      <h2 class="font-medium text-text-strong">
        {{ t('home.first_run.setup_title') }}
      </h2>
      <p class="mt-1 text-meta text-text-muted">
        {{ t('home.first_run.setup_body') }}
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
// The first-run state, in place of three empty blocks.
//
// Both buttons open the create sheet rather than navigating, because neither
// "add a booking" nor "add an enquiry" is a screen that exists yet. The sheet
// already carries the same TODO CreateMenu does, so this adds no second
// promise: it takes the artist to the one place the app admits creating is not
// built, rather than to a route that would 404 or to a button that does
// nothing.
import { useI18n } from 'vue-i18n'

const emit = defineEmits<{
  create: []
}>()

const { t } = useI18n()
</script>
