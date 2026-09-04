<template>
  <AuthCard :title="t('auth.register_title')">
    <form
      ref="form"
      class="space-y-6"
      novalidate
      @submit.prevent="submit"
    >
      <FormField
        v-slot="field"
        :label="t('auth.business_name_label')"
        :error="errors.business_name"
      >
        <TextInput
          v-bind="field"
          v-model="businessName"
          autocomplete="organization"
        />
      </FormField>

      <FormField
        v-slot="field"
        :label="t('auth.name_label')"
        :error="errors.name"
      >
        <TextInput
          v-bind="field"
          v-model="name"
          autocomplete="name"
        />
      </FormField>

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

      <!--
        The tick or cross inside the field is TextInput's status; the same
        thing in words is the field's statusMessage, which is announced and
        not shown. One without the other is a mark nobody can hear.
      -->
      <FormField
        v-slot="field"
        :label="t('auth.username_label')"
        :hint="usernameHint"
        :status-message="usernameStatusMessage"
        :error="errors.username"
      >
        <TextInput
          v-bind="field"
          v-model="username"
          autocomplete="username"
          :status="usernameStatus"
          @update:model-value="usernameTyped = true"
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
          autocomplete="new-password"
        />
      </FormField>

      <CheckboxInput
        id="marketing-consent"
        v-model="marketingConsent"
        :label="t('auth.marketing_consent_label')"
      />

      <FormError :message="formError" />

      <AppButton
        class="w-full"
        type="submit"
        :pending="pending"
      >
        {{ t('auth.register_action') }}
      </AppButton>
    </form>

    <!--
      The rule and the link set their own spacing rather than taking the
      card's, so that the gap above the rule can be a little larger than the
      one below it. Text sits low in its line box, so equal gaps on either
      side of the rule do not look equal.
    -->
    <div class="space-y-5 pt-1">
      <hr class="border-t border-neutral-200">

      <p class="text-center text-sm">
        <RouterLink
          class="font-medium text-brand hover:text-brand-strong"
          :to="{ name: 'login' }"
        >
          {{ t('auth.sign_in_link') }}
        </RouterLink>
      </p>
    </div>
  </AuthCard>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, useTemplateRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRouter } from 'vue-router'
import AuthCard from '@/components/AuthCard.vue'
import CheckboxInput from '@/components/form/CheckboxInput.vue'
import FormError from '@/components/form/FormError.vue'
import FormField from '@/components/form/FormField.vue'
import TextInput from '@/components/form/TextInput.vue'
import AppButton from '@/components/ui/AppButton.vue'
import { ApiError } from '@/lib/api'
import { focusFirstInvalid } from '@/lib/form'
import { deriveUsername } from '@/lib/username'
import { useAuthStore } from '@/stores/auth'
import type { UsernameReason } from '@/types/auth'

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()

const form = useTemplateRef<HTMLFormElement>('form')

const businessName = ref('')
const name = ref('')
const email = ref('')
const username = ref('')
const password = ref('')
const marketingConsent = ref(false)
const pending = ref(false)
const errors = ref<Record<string, string>>({})
const formError = ref<string | null>(null)

// Until the person types in the username field themselves, it follows the
// business name. The API derives the same thing when no username is sent.
//
// The control emits update:modelValue only when someone types in it, so the
// watcher below can write the field without that counting as typing.
const usernameTyped = ref(false)

watch(businessName, (value) => {
  if (!usernameTyped.value) {
    username.value = deriveUsername(value)
  }
})

// Availability is checked 300 ms after the last keystroke once there are
// three characters. Each request is numbered so that an answer arriving
// after a newer request was sent is ignored.
const availability = ref<{ available: boolean, reason: UsernameReason } | null>(null)
let checkTimer: ReturnType<typeof setTimeout> | null = null
let latestCheck = 0

watch(username, (value) => {
  availability.value = null

  if (checkTimer !== null) {
    clearTimeout(checkTimer)
    checkTimer = null
  }

  const candidate = value.toLowerCase()

  if (candidate.length < 3) {
    return
  }

  checkTimer = setTimeout(async () => {
    const sequence = ++latestCheck

    try {
      const result = await auth.checkUsername(candidate)

      if (sequence === latestCheck) {
        availability.value = result
      }
    } catch {
      // A failed check shows nothing; registration validates the name anyway.
    }
  }, 300)
})

onBeforeUnmount(() => {
  if (checkTimer !== null) {
    clearTimeout(checkTimer)
  }
})

const usernameHint = computed(() => {
  const candidate = username.value.toLowerCase()

  return candidate === '' ? undefined : t('auth.username_hint', { username: candidate })
})

// A tick or a cross inside the field.
const usernameStatus = computed(() => {
  if (availability.value === null) {
    return undefined
  }

  return availability.value.available ? 'valid' as const : 'invalid' as const
})

// The same thing in words, for a screen reader.
const usernameStatusMessage = computed(() => {
  if (availability.value === null) {
    return undefined
  }

  return availabilityMessage(availability.value.reason)
})

function availabilityMessage(reason: UsernameReason): string {
  switch (reason) {
    case 'invalid':
      return t('auth.username_invalid')
    case 'reserved':
      return t('auth.username_reserved')
    case 'taken':
      return t('auth.username_taken')
    default:
      return t('auth.username_available')
  }
}

async function submit(): Promise<void> {
  if (pending.value) {
    return
  }

  errors.value = {}
  formError.value = null

  if (availability.value !== null && !availability.value.available) {
    errors.value = { username: availabilityMessage(availability.value.reason) }
    await nextTick()
    focusFirstInvalid(form.value)

    return
  }

  pending.value = true

  try {
    await auth.register({
      business_name: businessName.value,
      name: name.value,
      email: email.value,
      username: username.value,
      password: password.value,
      marketing_consent: marketingConsent.value,
    })
    await router.push('/')
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
