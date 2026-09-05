<template>
  <Card v-if="auth.me && auth.me.user.email_verified_at === null">
    <div
      class="space-y-4 text-sm"
      role="status"
    >
      <p>{{ t('auth.email_unverified') }}</p>
      <p
        v-if="message"
        :class="messageIsError ? 'font-medium text-danger-text' : 'text-text-muted'"
      >
        {{ t(message) }}
      </p>
      <AppButton
        variant="secondary"
        size="small"
        :pending="pending"
        @click="resend"
      >
        {{ t('auth.resend_verification_action') }}
      </AppButton>
    </div>
  </Card>
</template>

<script setup lang="ts">
// Shown while the signed-in person's email address is unverified. A 204
// from the resend means the address was verified in the meantime, so the
// store is refreshed and the banner goes away on its own.
//
// Whether the message is a failure or a confirmation is carried by its
// weight and by what it says, not by a colour.
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
      ? 'common.too_many_attempts'
      : 'common.request_failed'
  } finally {
    pending.value = false
  }
}
</script>
