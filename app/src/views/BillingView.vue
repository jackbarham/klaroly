<template>
  <main class="p-6">
    <h1 class="text-2xl font-semibold">
      {{ t('billing.title') }}
    </h1>
  </main>
</template>

<script setup lang="ts">
// Web target only. See the routes array in src/router/index.ts.
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>
