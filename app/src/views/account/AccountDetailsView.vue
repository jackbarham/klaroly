<template>
  <div>
    <PageHeader
      :title="t('account.details')"
      :description="t('account.details_description')"
      :back-to="{ name: 'account' }"
    />

    <form
      ref="form"
      class="space-y-12"
      novalidate
      @submit.prevent="save"
    >
      <FormSection
        :title="t('account.you_section_title')"
        :description="t('account.you_section_description')"
      >
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
          The warning goes under the field before the change, not after it.
          Finding out that an address has been un-verified once the save has
          already happened is finding out too late to decide differently.
        -->
        <FormField
          v-slot="field"
          :label="t('auth.email_label')"
          :hint="t('account.email_change_hint')"
          :error="errors.email"
        >
          <TextInput
            v-bind="field"
            v-model="email"
            type="email"
            autocomplete="email"
          />
        </FormField>
      </FormSection>

      <FormSection
        :title="t('account.business_section_title')"
        :description="t('account.business_section_description')"
      >
        <!--
          A collaborator sees the field disabled with a hint saying why,
          rather than discovering the rule by being refused. The 403 is still
          handled below, because the answer can change between this page
          loading and the save being sent.
        -->
        <FormField
          v-slot="field"
          :label="t('auth.business_name_label')"
          :hint="canEditBusinessName ? t('account.business_name_hint') : t('account.business_name_owner_only')"
          :error="errors.business_name"
        >
          <TextInput
            v-bind="field"
            v-model="businessName"
            autocomplete="organization"
            :disabled="!canEditBusinessName"
          />
        </FormField>
      </FormSection>

      <p
        v-if="saved"
        class="text-sm text-text-muted"
        role="status"
      >
        {{ t('account.details_saved') }}
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
// Your name, your email and the business name, in one form and two requests:
// the first two belong to the user and the third to the account, and the API
// has an endpoint for each. Only the half that changed is sent, so renaming
// the business does not touch the profile endpoint and cannot un-verify an
// address nobody edited.
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError } from '@/lib/api'
import { useSubmit } from '@/lib/form'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const auth = useAuthStore()
const { form, pending, errors, formError, submit } = useSubmit()

const name = ref('')
const email = ref('')
const businessName = ref('')
const saved = ref(false)

const canEditBusinessName = computed(() => auth.me?.membership.role === 'owner')

// The optional chaining is the type rather than a real absence: me is
// Me | null because null is the signed-out state, and the router guard awaits
// bootstrap() before the first navigation, so an authenticated view never
// mounts without one. Writing auth.me! instead would only move the untruth.
function fillFromStore(): void {
  name.value = auth.me?.user.name ?? ''
  email.value = auth.me?.user.email ?? ''
  businessName.value = auth.me?.account.name ?? ''
}

// Cancel undoes the edits rather than leaving the page, which is why it also
// clears the confirmation: the fields are back to the last saved state and
// nothing has just been saved. The phone's way out is the header's back link.
function reset(): void {
  fillFromStore()
  saved.value = false
}

fillFromStore()

// Each of the two writes this form makes answers with a me payload, and the
// store replaces its copy from it. The fields have to pick up what came back,
// and on this form that is also the new baseline for what counts as changed
// on the next save.
//
// Only the fields: clearing the confirmation here would clear the message the
// save it is reacting to has just set.
watch(() => auth.me, fillFromStore)

// Which request rejected. The two endpoints both answer with a field called
// "name", one meaning the person and one the business, so without knowing
// which one spoke a message about the business would land on the person's
// field.
type Step = 'profile' | 'business'

let failedStep: Step = 'profile'

// Set once the profile request has been accepted, so that a failure after
// that point can say the business name was not saved without implying the
// name and email were not either.
let profileSaved = false

async function save(): Promise<void> {
  saved.value = false

  const profileChanged = name.value !== auth.me?.user.name || email.value !== auth.me?.user.email
  const businessChanged = canEditBusinessName.value && businessName.value !== auth.me?.account.name

  if (!profileChanged && !businessChanged) {
    saved.value = true

    return
  }

  profileSaved = false

  await submit(async () => {
    if (profileChanged) {
      failedStep = 'profile'
      await auth.updateProfile(name.value, email.value)
      profileSaved = true
    }

    if (businessChanged) {
      failedStep = 'business'
      await auth.updateBusinessName(businessName.value)
    }

    saved.value = true
  }, handleRejection)
}

// Only a failure of the second request is this screen's business. The first
// one is a plain profile rejection and useSubmit already knows what to do
// with it.
//
// Every message here is one whole sentence rather than a saved half and a
// failed half glued together, because two translated fragments joined in code
// are two fragments a translator cannot reorder.
async function handleRejection(error: ApiError): Promise<boolean> {
  if (failedStep !== 'business') {
    return false
  }

  if (error.status === 422) {
    // The account endpoint's "name" is the business name; this form's "name"
    // is the person's, so the message is moved to the field it is about.
    errors.value = { business_name: error.validationErrors().name ?? t('common.request_failed') }
    formError.value = profileSaved ? t('account.profile_saved') : null

    return true
  }

  if (error.status === 403) {
    formError.value = profileSaved
      ? t('account.business_refused_profile_saved')
      : t('account.business_refused')

    return true
  }

  if (profileSaved) {
    formError.value = t('account.business_failed_profile_saved')

    return true
  }

  return false
}
</script>
