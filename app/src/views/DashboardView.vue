<template>
  <main class="p-6">
    <h1 class="text-2xl font-semibold">
      {{ t('dashboard.title') }}
    </h1>
    <p class="mt-4 text-ink-muted">
      {{ t('dashboard.empty_state') }}
    </p>
  </main>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>
