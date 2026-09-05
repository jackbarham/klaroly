<template>
  <AuthCard :title="t('auth.forgot_password_title')">
    <p
      v-if="sent"
      class="text-sm"
      role="status"
    >
      {{ t('auth.reset_link_sent') }}
    </p>

    <form
      v-else
      ref="form"
      class="space-y-6"
      novalidate
      @submit.prevent="send"
    >
      <p class="text-sm text-text-muted">
        {{ t('auth.forgot_password_intro') }}
      </p>

      <FormField
        v-slot="field"
        :label="t('auth.email_label')"
        :error="errors.email"
      >
        <TextInput
          v-bind="field"
          v-model="email"
          type="email"
          autocomplete="email"
        />
      </FormField>

      <FormError :message="formError" />

      <AppButton
        class="w-full"
        type="submit"
        :pending="pending"
      >
        {{ t('auth.send_reset_link_action') }}
      </AppButton>
    </form>

    <p class="text-sm">
      <RouterLink
        class="rounded-control font-medium text-accent-text hover:underline focus-visible:focus-ring"
        :to="{ name: 'login' }"
      >
        {{ t('auth.sign_in_link') }}
      </RouterLink>
    </p>
  </AuthCard>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import AuthCard from '@/components/AuthCard.vue'
import { useSubmit } from '@/lib/form'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const auth = useAuthStore()
const { pending, errors, formError, submit } = useSubmit()

const email = ref('')
const sent = ref(false)

// The API answers the same way for a known and an unknown address, and so
// does this screen.
async function send(): Promise<void> {
  await submit(async () => {
    await auth.forgotPassword(email.value)
    sent.value = true
  })
}
</script>
