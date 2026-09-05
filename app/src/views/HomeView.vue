<template>
  <div>
    <PageHeader
      :title="t('home.title')"
      :description="auth.me ? t('home.greeting', { account: auth.me.account.name }) : undefined"
    />

    <div class="space-y-6">
      <p
        v-if="verifiedMessage"
        class="rounded-card border border-border bg-surface-raised p-4 text-sm text-text"
        role="status"
      >
        {{ t('auth.email_verified') }}
      </p>

      <VerificationBanner />

      <Card>
        <EmptyState :text="t('home.empty_state')" />
      </Card>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import VerificationBanner from '@/components/VerificationBanner.vue'
import Card from '@/components/ui/Card.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const verifiedMessage = ref(false)

// The verification link in the email ends on this page with ?verified=1.
// The store is refreshed so the banner goes, the message is shown once, and
// the query is replaced so a reload does not show it again.
onMounted(async () => {
  if (route.query.verified === '1') {
    verifiedMessage.value = true
    await router.replace({ name: 'home', query: {} })

    try {
      await auth.refresh()
    } catch {
      // A failed refresh leaves the banner as it was; the guard handles a 401.
    }
  }
})
</script>
