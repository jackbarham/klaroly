<template>
  <div>
    <PageHeader
      :title="t(titleKey)"
      :description="reference"
      :back-to="backTo"
    />
    <Card>
      <EmptyState :text="t('common.not_built_yet')" />
    </Card>
  </div>
</template>

<script setup lang="ts">
// Every section that has not been built yet. One component rather than a
// folder of identical files: a route says which title it wants and where
// back goes, and when a section is really built it gets a view of its own
// and the routes array points at that instead.
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'

const { t } = useI18n()
const route = useRoute()

const titleKey = computed(() => route.meta.titleKey ?? 'app.name')

const backTo = computed(() => (route.meta.backTo ? { name: route.meta.backTo } : undefined))

// A detail route echoes the id it was given. Nothing is looked up: there is
// no request behind any of these pages.
const reference = computed(() => {
  const id = route.params.id

  return typeof id === 'string' && id !== '' ? t('common.reference', { id }) : undefined
})
</script>
