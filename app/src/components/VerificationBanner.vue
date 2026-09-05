<template>
  <Card v-if="auth.me && auth.me.user.email_verified_at === null">
    <div
      class="space-y-4 text-sm"
      role="status"
    >
      <p>{{ t('auth.email_unverified') }}</p>
      <p
        v-if="message"
        :class="failed ? 'font-medium text-danger-text' : 'text-text-muted'"
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
// Shown while the signed-in person's email address is unverified. What the
// resend does with each of the API's three answers is useResendVerification,
// because the email page in My account offers the same thing.
//
// Whether the message is a failure or a confirmation is carried by its
// weight and by what it says, not by a colour.
import { useI18n } from 'vue-i18n'
import { useResendVerification } from '@/lib/verification'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const auth = useAuthStore()
const { pending, message, failed, resend } = useResendVerification()
</script>
