<template>
  <div>
    <PageHeader :title="t('billing.title')" />
    <Card>
      <EmptyState :text="t('common.not_built_yet')" />
    </Card>
  </div>
</template>

<script setup lang="ts">
// Web target only. See the routes array in src/router/index.ts.
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>
