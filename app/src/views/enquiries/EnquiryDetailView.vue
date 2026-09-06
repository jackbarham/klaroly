<template>
  <div>
    <!--
      The header draws from the list row the moment a row is tapped, and the
      second request fills in the rest. There is no empty state and no spinner
      in between: every field in the header is on the row already, so making
      the artist wait to see the name and the date would be waiting for nothing.
    -->
    <EnquiryDetail
      v-if="enquiry"
      :enquiry="enquiry"
      :detail="detail"
      :features="features"
      :today="today"
      @action="onAction"
    />

    <p
      v-else-if="enquiries.status === 'loading' || enquiries.detailStatus === 'loading'"
      class="py-12 text-center text-body text-text-muted"
      role="status"
    >
      {{ t('enquiries.loading') }}
    </p>

    <EmptyState
      v-else
      icon="enquiries"
      :text="t('enquiries.detail.not_found')"
    />
  </div>
</template>

<script setup lang="ts">
// One enquiry, resolved from the id in the address bar rather than from
// whatever the list happened to have selected, so a deep link and a hard
// reload both land on the right record.
//
// It reads the row from the list when the list has it, which is what lets the
// header draw before the detail request resolves. A deep link into a record
// the list has not loaded falls back to the detail response itself, which is
// the same shape plus three fields.
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import EnquiryDetail from '@/components/enquiries/EnquiryDetail.vue'
import { useAuthStore } from '@/stores/auth'
import { useEnquiriesStore } from '@/stores/enquiries'

defineProps<{
  // Passed down rather than read again here, so the header and the row beside
  // it cannot disagree about what today is across midnight.
  today: Date
}>()

const emit = defineEmits<{
  action: [key: string, id: number]
}>()

const { t } = useI18n()
const enquiries = useEnquiriesStore()
const auth = useAuthStore()
const route = useRoute()

const id = computed(() => {
  const value = Number(route.params.id)

  return Number.isFinite(value) && value > 0 ? value : null
})

// The list row first, because it is already in memory. The detail response is
// the same shape with three more fields, so it stands in on a deep link.
const enquiry = computed(() => (id.value === null ? null : enquiries.find(id.value) ?? enquiries.detail))

const detail = computed(() => (enquiries.detail?.id === id.value ? enquiries.detail : null))

const features = computed(() => auth.me?.features ?? null)

watch(id, (value) => {
  if (value === null) {
    enquiries.closeDetail()

    return
  }

  enquiries.openDetail(value)
}, { immediate: true })

function onAction(key: string): void {
  if (id.value !== null) {
    emit('action', key, id.value)
  }
}
</script>
