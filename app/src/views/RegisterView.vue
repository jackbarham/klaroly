<template>
  <AuthCard :title="t('auth.register_title')">
    <!--
      Two steps, one form, one route. The step is state on this screen rather
      than a route of its own, so a half-finished registration cannot be
      linked to, reloaded or landed on with the browser's back button, and
      nothing has to be carried between two views.
    -->
    <form
      ref="form"
      class="space-y-6"
      novalidate
      @submit.prevent="onSubmit"
    >
      <template v-if="step === 'account'">
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
            autocomplete="new-password"
          />
        </FormField>

        <AppButton
          class="w-full"
          type="submit"
          icon-end="chevron-right"
        >
          {{ t('auth.register_next_action') }}
        </AppButton>
      </template>

      <template v-else>
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
          inline
          :label="t('auth.marketing_consent_label')"
        >
          <CheckboxInput
            v-bind="field"
            v-model="marketingConsent"
          />
        </FormField>

        <FormError :message="formError" />

        <!--
          An auto-width secondary and a primary that fills the rest, which is
          the style guide's action row. It reads the same on a phone as it
          does here, unlike a right-aligned pair.
        -->
        <div class="flex gap-2">
          <AppButton
            variant="secondary"
            icon="chevron-left"
            @click="goTo('account')"
          >
            {{ t('common.back') }}
          </AppButton>
          <AppButton
            class="grow"
            type="submit"
            :pending="pending"
          >
            {{ t('auth.register_action') }}
          </AppButton>
        </div>
      </template>
    </form>

    <!--
      The rule and the link set their own spacing rather than taking the
      card's, so that the gap above the rule can be a little larger than the
      one below it. Text sits low in its line box, so equal gaps on either
      side of the rule do not look equal.
    -->
    <div class="space-y-5 pt-1">
      <hr class="border-t border-border">

      <p class="text-center text-sm">
        <RouterLink
          class="rounded-control font-medium text-accent-text hover:underline focus-visible:focus-ring"
          :to="{ name: 'login' }"
        >
          {{ t('auth.sign_in_link') }}
        </RouterLink>
      </p>
    </div>
  </AuthCard>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRouter } from 'vue-router'
import AuthCard from '@/components/AuthCard.vue'
import { ApiError } from '@/lib/api'
import { useSubmit } from '@/lib/form'
import { deriveUsername } from '@/lib/username'
import { useAuthStore } from '@/stores/auth'
import type { UsernameCheck, UsernameReason } from '@/types/auth'

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()
const { form, pending, errors, formError, submit, reject } = useSubmit()

const businessName = ref('')
const name = ref('')
const email = ref('')
const username = ref('')
const password = ref('')
const marketingConsent = ref(false)

// Which half of the form is on screen. The first step is what someone needs
// to sign in with, the second is who they are; splitting it that way keeps
// the first screen to the two fields every account has.
type Step = 'account' | 'business'

const step = ref<Step>('account')

// Which step owns a field, for sending a rejected registration back to the
// step that can show the message. The rest belong to the second step.
const accountFields = ['email', 'password']

async function goTo(next: Step): Promise<void> {
  step.value = next
  await nextTick()

  // The button that was clicked has just been removed, which would leave
  // focus on the body and a keyboard user nowhere.
  form.value?.querySelector('input')?.focus()
}

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
const availability = ref<UsernameCheck | null>(null)
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

// Enter and the button do the same thing on both steps: the first one moves
// on, the second one registers. Nothing is sent from the first step, because
// there is no route that validates half an account; an email or a password
// the API turns down comes back below.
async function onSubmit(): Promise<void> {
  if (step.value === 'account') {
    await goTo('business')

    return
  }

  await register()
}

async function register(): Promise<void> {
  // A username the live check has already turned down is not sent.
  if (availability.value !== null && !availability.value.available) {
    await reject({ username: availabilityMessage(availability.value.reason) })

    return
  }

  await submit(async () => {
    await auth.register({
      business_name: businessName.value,
      name: name.value,
      email: email.value,
      username: username.value,
      password: password.value,
      marketing_consent: marketingConsent.value,
    })
    await router.push('/')
  }, handleRejection)
}

// A field on the first step cannot show its message while the second step is
// on screen, so a rejection naming one takes the form back there. reject()
// then puts the messages on the fields and moves focus to the first of them,
// which is now rendered.
async function handleRejection(error: ApiError): Promise<boolean> {
  if (error.status !== 422) {
    return false
  }

  const fieldErrors = error.validationErrors()

  if (accountFields.some((field) => field in fieldErrors)) {
    step.value = 'account'
  }

  await reject(fieldErrors)

  return true
}
</script>
