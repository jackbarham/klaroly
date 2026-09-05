<template>
  <div>
    <PageHeader
      :title="t('account.email')"
      :description="t('account.email_description')"
      :back-to="{ name: 'account' }"
    />

    <div class="space-y-6">
      <Card>
        <div class="space-y-4">
          <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0">
              <p class="text-sm text-text-muted">
                {{ t('auth.email_label') }}
              </p>
              <p class="truncate text-text-strong">
                {{ auth.me?.user.email }}
              </p>
            </div>
            <StatusPill :tone="verified ? 'success' : 'warning'">
              {{ verified ? t('account.email_verified') : t('account.email_unverified') }}
            </StatusPill>
          </div>

          <p class="text-sm text-text-muted">
            {{ t('account.email_change_where') }}
          </p>

          <!--
            The address itself is changed on the details page, so this one is
            read-only apart from the resend. What the resend does with each of
            the API's three answers is useResendVerification, which the home
            page banner uses too.
          -->
          <template v-if="!verified">
            <p
              v-if="resendMessage"
              class="text-sm"
              :class="resendFailed ? 'font-medium text-danger-text' : 'text-text-muted'"
              role="status"
            >
              {{ t(resendMessage) }}
            </p>
            <AppButton
              variant="secondary"
              size="small"
              :pending="resendPending"
              @click="resend"
            >
              {{ t('auth.resend_verification_action') }}
            </AppButton>
          </template>
        </div>
      </Card>

      <FormSection
        :title="t('account.marketing_section_title')"
        :description="t('account.marketing_section_description')"
      >
        <FormError :message="consentError" />

        <!--
          Saved the moment it is thrown, with no Save button. A consent toggle
          behind a confirmation is a consent toggle people leave in the state
          they did not want, having believed they had already changed it.
        -->
        <FormField
          v-slot="field"
          :label="t('account.marketing_label')"
          :hint="t('account.marketing_hint')"
        >
          <ToggleSwitch
            v-bind="field"
            :model-value="consented"
            :disabled="consentPending"
            @update:model-value="setConsent"
          />
        </FormField>
      </FormSection>
    </div>
  </div>
</template>

<script setup lang="ts">
// The address, whether it has been verified, and the one marketing consent.
// Nothing here changes the address: that is the details page, and saying so
// is better than a second field that does the same thing twice.
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useResendVerification } from '@/lib/verification'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const auth = useAuthStore()

const {
  pending: resendPending,
  message: resendMessage,
  failed: resendFailed,
  resend,
} = useResendVerification()

const verified = computed(() => auth.me !== null && auth.me.user.email_verified_at !== null)

// The consent is a dated fact on the user, so the switch is on when there is
// a date. The store's copy is replaced from the same response the write
// answers with, so nothing here re-reads it.
const consented = ref(auth.me?.user.marketing_consent_at != null)
const consentPending = ref(false)
const consentError = ref<string | null>(null)

// The write below answers with a me payload and the store replaces its copy
// from it, so the switch follows what came back rather than only what was
// clicked.
//
// Not while a write is in flight: the switch has already moved to the value
// being sent, and a store update landing in the middle would drag it back to
// the old one and then forward again.
watch(() => auth.me?.user.marketing_consent_at, (value) => {
  if (!consentPending.value) {
    consented.value = value != null
  }
})

// The switch moves first and the request follows, because a toggle that waits
// for a round trip before moving reads as a toggle that did not work. There is
// no watcher on the value: the change comes from the control, so the handler
// is where it is, and putting it back after a failure cannot then look like
// somebody changing their mind a second time.
async function setConsent(value: boolean): Promise<void> {
  if (consentPending.value) {
    return
  }

  const previous = consented.value

  consented.value = value
  consentPending.value = true
  consentError.value = null

  try {
    await auth.setMarketingConsent(value)
  } catch {
    // A switch showing a state the API does not hold is worse than no switch
    // at all, so it goes back to what it was and says why.
    consented.value = previous
    consentError.value = t('account.marketing_failed')
  } finally {
    consentPending.value = false
  }
}
</script>
