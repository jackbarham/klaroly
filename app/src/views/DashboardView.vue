<template>
  <main class="mx-auto max-w-2xl space-y-6 p-6">
    <div class="flex items-start justify-between gap-4">
      <div>
        <h1 class="text-2xl font-semibold">
          {{ t('dashboard.title') }}
        </h1>
        <p
          v-if="auth.me"
          class="mt-1 text-ink-muted"
        >
          {{ t('dashboard.greeting', { account: auth.me.account.name }) }}
        </p>
      </div>
      <button
        class="rounded-control border border-line px-3 py-1 text-sm hover:bg-surface-raised"
        type="button"
        @click="signOut"
      >
        {{ t('auth.sign_out_action') }}
      </button>
    </div>

    <p
      v-if="verifiedMessage"
      class="rounded-card border border-line bg-surface-raised p-4 text-sm text-success"
      role="status"
    >
      {{ t('auth.email_verified') }}
    </p>

    <VerificationBanner />

    <p class="text-ink-muted">
      {{ t('dashboard.empty_state') }}
    </p>
  </main>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import VerificationBanner from '@/components/VerificationBanner.vue'
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
    await router.replace({ name: 'dashboard', query: {} })

    try {
      await auth.refresh()
    } catch {
      // A failed refresh leaves the banner as it was; the guard handles a 401.
    }
  }
})

async function signOut(): Promise<void> {
  await auth.signOut()
  await router.push({ name: 'login' })
}
</script>
