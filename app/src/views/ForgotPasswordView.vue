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
      @submit.prevent="submit"
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
        class="font-medium text-accent-text hover:underline"
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
import FormError from '@/components/form/FormError.vue'
import FormField from '@/components/form/FormField.vue'
import TextInput from '@/components/form/TextInput.vue'
import AppButton from '@/components/ui/AppButton.vue'
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
