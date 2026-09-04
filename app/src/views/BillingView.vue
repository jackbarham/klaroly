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
import Card from '@/components/ui/Card.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import PageHeader from '@/components/ui/PageHeader.vue'

const { t } = useI18n()
</script>
