<template>
  <AuthCard :title="t('auth.sign_in_title')">
    <p
      v-if="notice"
      class="text-sm text-text-muted"
      role="status"
    >
      {{ t(notice) }}
    </p>

    <form
      ref="form"
      class="space-y-6"
      novalidate
      @submit.prevent="signIn"
    >
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

      <FormField
        v-slot="field"
        :label="t('auth.password_label')"
        :error="errors.password"
      >
        <TextInput
          v-bind="field"
          v-model="password"
          type="password"
          autocomplete="current-password"
        />
      </FormField>

      <CheckboxInput
        v-if="isWeb"
        id="remember"
        v-model="remember"
        :label="t('auth.remember_label')"
      />

      <FormError :message="formError" />

      <AppButton
        class="w-full"
        type="submit"
        :pending="pending"
      >
        {{ t('auth.sign_in_action') }}
      </AppButton>
    </form>

    <!--
      The rule and the links set their own spacing rather than taking the
      card's, so that the gap above the rule can be a little larger than the
      one below it. Text sits low in its line box, so equal gaps on either
      side of the rule do not look equal. Same as the register screen.
    -->
    <div class="space-y-5 pt-1">
      <hr class="border-t border-border">

      <div class="space-y-2 text-center text-sm">
        <p>
          <RouterLink
            class="rounded-control font-medium text-accent-text hover:underline focus-visible:focus-ring"
            :to="{ name: 'forgot-password' }"
          >
            {{ t('auth.forgot_password_link') }}
          </RouterLink>
        </p>
        <p>
          <RouterLink
            class="rounded-control font-medium text-accent-text hover:underline focus-visible:focus-ring"
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
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import AuthCard from '@/components/AuthCard.vue'
import { useSubmit } from '@/lib/form'
import { isWeb } from '@/lib/platform'
import { destinationAfterSignIn } from '@/router'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const { pending, errors, formError, submit } = useSubmit()

const email = ref('')
const password = ref('')
const remember = ref(true)

// A message left by the store, shown once and then forgotten.
const notice = ref(auth.takeNotice())

async function signIn(): Promise<void> {
  notice.value = null

  await submit(async () => {
    await auth.signIn(email.value, password.value, remember.value)
    await router.push(destinationAfterSignIn(route.query.redirect))
  }, (error) => {
    // A rejected credential clears the password, so the next attempt starts
    // from an empty box. The message itself lands on the field as usual.
    if (error.status === 422) {
      password.value = ''
    }

    // The store has already ended a login that belongs to no account and
    // left the notice saying so.
    if (error.status === 403) {
      notice.value = auth.takeNotice()

      return true
    }

    return false
  })
}
</script>
