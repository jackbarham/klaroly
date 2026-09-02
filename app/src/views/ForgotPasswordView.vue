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
      class="space-y-4"
      novalidate
      @submit.prevent="submit"
    >
      <p class="text-sm text-ink-muted">
        {{ t('auth.forgot_password_intro') }}
      </p>

      <TextField
        id="email"
        v-model="email"
        :label="t('auth.email_label')"
        type="email"
        autocomplete="email"
        :error="errors.email"
      />

      <FormError :message="formError" />

      <SubmitButton :pending="pending">
        {{ t('auth.send_reset_link_action') }}
      </SubmitButton>
    </form>

    <p class="text-sm">
      <RouterLink
        class="text-brand hover:text-brand-strong"
        :to="{ name: 'login' }"
      >
        {{ t('auth.sign_in_link') }}
      </RouterLink>
    </p>
  </AuthCard>
</template>

<script setup lang="ts">
import { nextTick, ref, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import AuthCard from '@/components/AuthCard.vue'
import FormError from '@/components/FormError.vue'
import SubmitButton from '@/components/SubmitButton.vue'
import TextField from '@/components/TextField.vue'
import { ApiError } from '@/lib/api'
import { focusFirstInvalid } from '@/lib/form'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const auth = useAuthStore()

const form = useTemplateRef<HTMLFormElement>('form')

const email = ref('')
const pending = ref(false)
const sent = ref(false)
const errors = ref<Record<string, string>>({})
const formError = ref<string | null>(null)

async function submit(): Promise<void> {
  if (pending.value) {
    return
  }

  pending.value = true
  errors.value = {}
  formError.value = null

  try {
    // The API answers the same way for a known and an unknown address, and
    // so does this screen.
    await auth.forgotPassword(email.value)
    sent.value = true
  } catch (error) {
    if (error instanceof ApiError && error.status === 422) {
      errors.value = error.validationErrors()
    } else if (error instanceof ApiError && error.status === 429) {
      formError.value = t('auth.too_many_attempts')
    } else {
      formError.value = t('auth.request_failed')
    }

    await nextTick()
    focusFirstInvalid(form.value)
  } finally {
    pending.value = false
  }
}
</script>
