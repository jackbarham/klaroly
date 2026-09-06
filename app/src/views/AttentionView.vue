<template>
  <div class="@container">
    <PageHeader
      :title="t('home.attention.title')"
      :back-to="{ name: 'home' }"
    />

    <p
      v-if="home.status === 'failed'"
      class="mb-4 flex flex-wrap items-center gap-3 rounded-card border border-border bg-surface-raised p-4 text-body text-text"
      role="status"
    >
      {{ t('home.load_failed') }}
      <AppButton
        variant="secondary"
        size="small"
        :pending="retrying"
        @click="onRetry"
      >
        {{ t('home.retry') }}
      </AppButton>
    </p>

    <!--
      **No cap here, so the band headings and the account total are the same
      number.** That is the whole difference from the preview: same component,
      same grouping, same order, and a limit of null.
    -->
    <div
      v-else-if="home.summary"
      class="-mx-6 @split:mx-0"
    >
      <AttentionList
        v-if="home.summary.attention.length > 0"
        :rows="home.summary.attention"
        :today="home.today"
        :limit="null"
      />

      <EmptyState
        v-else
        icon="check"
        :text="t('home.attention.all_clear')"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
// The full attention list, business logic 18.1 without the preview cap.
//
// **A route rather than a flag on Home** (decision 2026-09-06.1942): a
// notification can link straight to it, the back gesture works, and a view flag
// cannot be linked to. It carries no tab of its own and marks Home in the
// navigation, the way a booking's page marks Bookings.
//
// It reads the same store as Home, so arriving here directly fetches once and
// arriving from Home fetches not at all.
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AttentionList from '@/components/home/AttentionList.vue'
import { useHomeStore } from '@/stores/home'

const { t } = useI18n()
const home = useHomeStore()

const retrying = ref(false)

async function onRetry(): Promise<void> {
  retrying.value = true

  try {
    await home.retry()
  } finally {
    retrying.value = false
  }
}

onMounted(() => {
  void home.load()
})
</script>
