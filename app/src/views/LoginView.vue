<template>
  <AuthCard :title="t('auth.sign_in_title')">
    <p
      v-if="notice"
      class="text-sm text-ink-muted"
      role="status"
    >
      {{ t(notice) }}
    </p>

    <form
      ref="form"
      class="space-y-4"
      novalidate
      @submit.prevent="submit"
    >
      <TextField
        id="email"
        v-model="email"
        :label="t('auth.email_label')"
        type="email"
        autocomplete="email"
        :error="errors.email"
      />

      <TextField
        id="password"
        v-model="password"
        :label="t('auth.password_label')"
        type="password"
        autocomplete="current-password"
      />

      <CheckboxField
        v-if="isWeb"
        id="remember"
        v-model="remember"
        :label="t('auth.remember_label')"
      />

      <FormError :message="formError" />

      <SubmitButton :pending="pending">
        {{ t('auth.sign_in_action') }}
      </SubmitButton>
    </form>

    <!--
      The rule and the links set their own spacing rather than taking the
      card's, so that the gap above the rule can be a little larger than the
      one below it. Text sits low in its line box, so equal gaps on either
      side of the rule do not look equal. Same as the register screen.
    -->
    <div class="space-y-5 pt-1">
      <hr class="border-t border-line">

      <div class="space-y-2 text-center text-sm">
        <p>
          <RouterLink
            class="font-medium text-brand hover:text-brand-strong"
            :to="{ name: 'forgot-password' }"
          >
            {{ t('auth.forgot_password_link') }}
          </RouterLink>
        </p>
        <p>
          <RouterLink
            class="font-medium text-brand hover:text-brand-strong"
            :to="{ name: 'register' }"
          >
            {{ t('auth.register_link') }}
          </RouterLink>
        </p>
      </div>
    </div>
  </AuthCard>
</template>

<script setup lang="ts">
import { nextTick, ref, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import AuthCard from '@/components/AuthCard.vue'
import CheckboxField from '@/components/CheckboxField.vue'
import FormError from '@/components/FormError.vue'
import SubmitButton from '@/components/SubmitButton.vue'
import TextField from '@/components/TextField.vue'
import { ApiError } from '@/lib/api'
import { focusFirstInvalid } from '@/lib/form'
import { isWeb } from '@/lib/platform'
import { destinationAfterSignIn } from '@/router'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const form = useTemplateRef<HTMLFormElement>('form')

const email = ref('')
const password = ref('')
const remember = ref(true)
const pending = ref(false)
const errors = ref<Record<string, string>>({})
const formError = ref<string | null>(null)

// A message left by the store, shown once and then forgotten.
const notice = ref(auth.takeNotice())

async function submit(): Promise<void> {
  if (pending.value) {
    return
  }

  pending.value = true
  errors.value = {}
  formError.value = null
  notice.value = null

  try {
    await auth.signIn(email.value, password.value, remember.value)
    await router.push(destinationAfterSignIn(route.query.redirect))
  } catch (error) {
    if (error instanceof ApiError && error.status === 422) {
      errors.value = error.validationErrors()
      password.value = ''
    } else if (error instanceof ApiError && error.status === 403) {
      notice.value = auth.takeNotice()
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
