import { ref, type Ref } from 'vue'
import { ApiError } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

// Asking for the verification email again. The banner on the home page and
// the email page in My account both offer it, and both have to cope with the
// same three answers, so the handling is here rather than written twice.
//
// A 202 means an email is on its way. A 204 means the address was verified in
// the meantime, so the store is refreshed and whatever offered the resend can
// take itself off the screen.
//
// The message is a locale key rather than a string, so the component decides
// how to show it and this file names no wording.

export interface Resend {
  pending: Ref<boolean>
  message: Ref<string | null>
  failed: Ref<boolean>
  resend: () => Promise<void>
}

export function useResendVerification(): Resend {
  const auth = useAuthStore()

  const pending = ref(false)
  const message = ref<string | null>(null)
  const failed = ref(false)

  async function resend(): Promise<void> {
    if (pending.value) {
      return
    }

    pending.value = true
    message.value = null
    failed.value = false

    try {
      const sent = await auth.resendVerification()

      if (sent) {
        message.value = 'auth.verification_sent'
      } else {
        await auth.refresh()
      }
    } catch (error) {
      failed.value = true
      message.value = error instanceof ApiError && error.status === 429
        ? 'common.too_many_attempts'
        : 'common.request_failed'
    } finally {
      pending.value = false
    }
  }

  return { pending, message, failed, resend }
}
