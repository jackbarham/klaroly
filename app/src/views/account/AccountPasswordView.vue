<template>
  <div>
    <PageHeader
      :title="t('account.password')"
      :description="t('account.password_description')"
      :back-to="{ name: 'account' }"
    />

    <form
      ref="form"
      class="space-y-12"
      novalidate
      @submit.prevent="save"
    >
      <FormSection :title="t('account.password_section_title')">
        <FormField
          v-slot="field"
          :label="t('account.current_password_label')"
          :error="errors.current_password"
        >
          <TextInput
            v-bind="field"
            v-model="currentPassword"
            type="password"
            autocomplete="current-password"
          />
        </FormField>

        <!--
          The rule is said before it is broken. Ten characters is what the API
          enforces, and finding that out from a rejection is finding it out
          after choosing a password.
        -->
        <FormField
          v-slot="field"
          :label="t('auth.new_password_label')"
          :hint="t('account.password_rule_hint')"
          :error="errors.password"
        >
          <TextInput
            v-bind="field"
            v-model="password"
            type="password"
            autocomplete="new-password"
          />
        </FormField>

        <FormField
          v-slot="field"
          :label="t('account.confirm_password_label')"
          :error="errors.password_confirmation"
        >
          <TextInput
            v-bind="field"
            v-model="passwordConfirmation"
            type="password"
            autocomplete="new-password"
          />
        </FormField>
      </FormSection>

      <!--
        Two things worth saying afterwards: this device is still signed in,
        which is not what people expect after changing a password, and the
        others are not, which is surprising to discover later.
      -->
      <p
        v-if="saved"
        class="text-sm text-text-muted"
        role="status"
      >
        {{ t('account.password_saved') }}
      </p>

      <FormError :message="formError" />

      <FormActions
        :pending="pending"
        @save="save"
        @cancel="reset"
      />
    </form>
  </div>
</template>

<script setup lang="ts">
// Changing a password from inside the app, which is not the same thing as
// resetting a forgotten one: this asks for the current password and the
// person stays signed in afterwards, because the API keeps the credential
// that made the request and revokes every other one.
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useSubmit } from '@/lib/form'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const auth = useAuthStore()
const { form, pending, errors, formError, submit, reject } = useSubmit()

const currentPassword = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const saved = ref(false)

function reset(): void {
  currentPassword.value = ''
  password.value = ''
  passwordConfirmation.value = ''
  saved.value = false
}

async function save(): Promise<void> {
  saved.value = false

  // A mismatch is something this screen can see for itself, so it says so
  // rather than spending a request to be told.
  if (password.value !== passwordConfirmation.value) {
    await reject({ password_confirmation: t('account.password_mismatch') })

    return
  }

  await submit(async () => {
    await auth.updatePassword(currentPassword.value, password.value)

    reset()
    saved.value = true
  })
}
</script>
