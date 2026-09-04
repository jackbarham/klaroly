<template>
  <div
    v-if="updateAvailable"
    class="fixed inset-x-0 bottom-0 z-10 flex items-center justify-center gap-4 bg-brand p-3 text-sm text-on-brand"
    role="status"
  >
    <p>{{ t('updates.available') }}</p>
    <button
      class="rounded-control border border-on-brand px-3 py-1 hover:bg-brand-strong disabled:opacity-60"
      type="button"
      :disabled="pending"
      @click="reload"
    >
      {{ t('updates.reload_action') }}
    </button>
  </div>
</template>

<script setup lang="ts">
// Web target only: shown when a newer build is waiting to take over. The
// person chooses when to reload, so nothing they are typing is lost.
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { applyUpdate, updateAvailable } from '@/lib/updates'

const { t } = useI18n()

const pending = ref(false)

async function reload(): Promise<void> {
  pending.value = true
  await applyUpdate()
}
</script>
