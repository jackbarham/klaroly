<template>
  <AuthCard :title="t('auth.reset_password_title')">
    <template v-if="linkInvalid">
      <FormError :message="t('auth.reset_link_invalid')" />

      <p class="text-sm">
        <RouterLink
          class="rounded-control font-medium text-accent-text hover:underline focus-visible:focus-ring"
          :to="{ name: 'forgot-password' }"
        >
          {{ t('auth.forgot_password_link') }}
        </RouterLink>
      </p>
    </template>

    <form
      v-else
      ref="form"
      class="space-y-6"
      novalidate
      @submit.prevent="reset"
    >
      <!--
        The address comes from the emailed link and cannot be changed, so it
        is shown rather than offered as a field. It is still submitted.
      -->
      <p>{{ email }}</p>

      <FormField
        v-slot="field"
        :label="t('auth.new_password_label')"
        :error="errors.password"
      >
        <TextInput
          v-bind="field"
          v-model="password"
          type="password"
          autocomplete="new-password"
        />
      </FormField>

      <FormError :message="formError" />

      <AppButton
        class="w-full"
        type="submit"
        :pending="pending"
      >
        {{ t('auth.reset_password_action') }}
      </AppButton>
    </form>
  </AuthCard>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import AuthCard from '@/components/AuthCard.vue'
import { useSubmit } from '@/lib/form'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const { pending, errors, formError, submit } = useSubmit()

// Both come from the link in the reset email.
const token = typeof route.query.token === 'string' ? route.query.token : ''
const email = typeof route.query.email === 'string' ? route.query.email : ''

const linkInvalid = ref(token === '' || email === '')
const password = ref('')

async function reset(): Promise<void> {
  await submit(async () => {
    await auth.resetPassword(token, email, password.value)
    await signInWithNewPassword()
  }, (error) => {
    if (error.status !== 422) {
      return false
    }

    const fieldErrors = error.validationErrors()

    // An error on the email field means the token is bad or expired.
    if (fieldErrors.email) {
      linkInvalid.value = true
    } else {
      errors.value = {
        password: fieldErrors.password ?? fieldErrors.token ?? t('common.request_failed'),
      }
    }

    return true
  })
}

// The password has been changed, so the person is signed in with it
// straight away rather than being sent to the login screen to type it
// again. If that sign-in fails for any reason, the login screen is the
// fallback, with a notice saying the change itself succeeded.
async function signInWithNewPassword(): Promise<void> {
  try {
    await auth.signIn(email, password.value, true)
  } catch {
    // The store may already have left a notice (for example a login that
    // belongs to no account). Only set ours when it has not.
    if (auth.notice === null) {
      auth.notice = 'auth.password_reset_done'
    }

    await router.push({ name: 'login' })

    return
  }

  await router.push({ name: 'home' })
}
</script>
