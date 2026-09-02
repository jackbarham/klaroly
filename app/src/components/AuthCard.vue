<template>
  <main class="flex min-h-screen flex-col items-center justify-center p-6">
    <p class="mb-6 text-2xl font-bold">
      {{ t('app.name') }}
    </p>
    <div class="w-full max-w-sm space-y-6 rounded-card border border-line bg-surface-raised p-8">
      <h1 class="text-xl font-semibold">
        {{ title }}
      </h1>
      <slot />
    </div>
  </main>
</template>

<script setup lang="ts">
// The centred card every authentication screen sits in, with the Klaroly
// wordmark above it. One column at every width (decision 10); the card
// simply narrows on a phone.
import { useI18n } from 'vue-i18n'

defineProps<{
  title: string
}>()

const { t } = useI18n()
</script>
