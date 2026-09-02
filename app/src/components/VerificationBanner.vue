<template>
  <div
    v-if="auth.me && auth.me.user.email_verified_at === null"
    class="space-y-2 rounded-card border border-line bg-surface-raised p-4 text-sm"
    role="status"
  >
    <p>{{ t('auth.email_unverified') }}</p>
    <p
      v-if="message"
      :class="messageIsError ? 'text-danger' : 'text-success'"
    >
      {{ t(message) }}
    </p>
    <button
      class="rounded-control border border-line px-3 py-1 hover:bg-surface disabled:opacity-60"
      type="button"
      :disabled="pending"
      @click="resend"
    >
      {{ t('auth.resend_verification_action') }}
    </button>
  </div>
</template>

<script setup lang="ts">
// Shown while the signed-in person's email address is unverified. A 204
// from the resend means the address was verified in the meantime, so the
// store is refreshed and the banner goes away on its own.
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const auth = useAuthStore()

const pending = ref(false)
const message = ref<string | null>(null)
const messageIsError = ref(false)

async function resend(): Promise<void> {
  if (pending.value) {
    return
  }

  pending.value = true
  message.value = null
  messageIsError.value = false

  try {
    const sent = await auth.resendVerification()

    if (sent) {
      message.value = 'auth.verification_sent'
    } else {
      await auth.refresh()
    }
  } catch (error) {
    messageIsError.value = true
    message.value = error instanceof ApiError && error.status === 429
      ? 'auth.too_many_attempts'
      : 'auth.request_failed'
  } finally {
    pending.value = false
  }
}
</script>
