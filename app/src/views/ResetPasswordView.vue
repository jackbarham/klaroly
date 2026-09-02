<template>
  <AuthCard :title="t('auth.reset_password_title')">
    <template v-if="done">
      <p
        class="text-sm"
        role="status"
      >
        {{ t('auth.password_reset_done') }}
      </p>
      <p class="text-sm">
        <RouterLink
          class="text-brand hover:text-brand-strong"
          :to="{ name: 'login' }"
        >
          {{ t('auth.sign_in_link') }}
        </RouterLink>
      </p>
    </template>

    <template v-else-if="linkInvalid">
      <p
        class="text-sm text-danger"
        role="alert"
      >
        {{ t('auth.reset_link_invalid') }}
      </p>
      <p class="text-sm">
        <RouterLink
          class="text-brand hover:text-brand-strong"
          :to="{ name: 'forgot-password' }"
        >
          {{ t('auth.forgot_password_link') }}
        </RouterLink>
      </p>
    </template>

    <form
      v-else
      ref="form"
      class="space-y-4"
      novalidate
      @submit.prevent="submit"
    >
      <!--
        The address comes from the emailed link and cannot be changed, so it
        is shown rather than offered as a field. It is still submitted.
      -->
      <p>{{ email }}</p>

      <TextField
        id="password"
        v-model="password"
        :label="t('auth.new_password_label')"
        type="password"
        autocomplete="new-password"
        :error="errors.password"
      />

      <FormError :message="formError" />

      <SubmitButton :pending="pending">
        {{ t('auth.reset_password_action') }}
      </SubmitButton>
    </form>
  </AuthCard>
</template>

<script setup lang="ts">
import { nextTick, ref, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'
import AuthCard from '@/components/AuthCard.vue'
import FormError from '@/components/FormError.vue'
import SubmitButton from '@/components/SubmitButton.vue'
import TextField from '@/components/TextField.vue'
import { ApiError } from '@/lib/api'
import { focusFirstInvalid } from '@/lib/form'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const route = useRoute()
const auth = useAuthStore()

const form = useTemplateRef<HTMLFormElement>('form')

// Both come from the link in the reset email.
const token = typeof route.query.token === 'string' ? route.query.token : ''
const email = ref(typeof route.query.email === 'string' ? route.query.email : '')

const linkInvalid = ref(token === '' || email.value === '')
const done = ref(false)
const password = ref('')
const pending = ref(false)
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
    await auth.resetPassword(token, email.value, password.value)
    done.value = true
  } catch (error) {
    if (error instanceof ApiError && error.status === 422) {
      const fieldErrors = error.validationErrors()

      // An error on the email field means the token is bad or expired.
      if (fieldErrors.email) {
        linkInvalid.value = true
      } else {
        errors.value = {
          password: fieldErrors.password ?? fieldErrors.token ?? t('auth.request_failed'),
        }
      }
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
