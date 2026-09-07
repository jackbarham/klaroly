<template>
  <p
    class="mb-4 flex flex-wrap items-center gap-3 rounded-card border border-border bg-surface-raised p-4 text-body text-text"
    role="status"
  >
    {{ message }}
    <AppButton
      variant="secondary"
      size="small"
      @click="emit('retry')"
    >
      {{ t('common.retry') }}
    </AppButton>
  </p>
</template>

<script setup lang="ts">
// A load that failed, said in words with something to do about it. Five
// screens draw this above whatever they could not load. The wording is the
// screen's own, because "those bookings" and "your summary" are different
// sentences, and the button is the same one everywhere.
//
// It is a status rather than an alert: nothing is lost, the screen is empty
// rather than wrong, and a screen reader is told without being interrupted.
// The screen decides when it shows, from its store's status, and what a retry
// does, because the bookings screen has two things that can fail and the
// others have one.
import { useI18n } from 'vue-i18n'
import AppButton from '@/components/ui/AppButton.vue'

defineProps<{
  message: string
}>()

const emit = defineEmits<{
  retry: []
}>()

const { t } = useI18n()
</script>
