import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { ApiError, onUnauthenticated } from '@/lib/api'
import * as auth from '@/lib/auth'
import { isNative } from '@/lib/platform'
import * as tokenStorage from '@/lib/tokenStorage'
import type { Me, RegisterFields } from '@/types/auth'

export type AuthStatus = 'unknown' | 'signed_out' | 'signed_in'

// Who is signed in. Screens read and change authentication through this
// store and nothing else; the store talks to src/lib/auth.ts.
export const useAuthStore = defineStore('auth', () => {
  const me = ref<Me | null>(null)
  const status = ref<AuthStatus>('unknown')

  // A locale key for a message the login screen shows once, for example
  // after signing out or when a login belongs to no account.
  const notice = ref<string | null>(null)

  const isAuthenticated = computed(() => status.value === 'signed_in')

  // A 401 from any request means the session or token is gone. Clearing
  // the state here is enough: the router guard sends the person to login.
  onUnauthenticated(() => {
    me.value = null
    status.value = 'signed_out'
  })

  function setSignedIn(value: Me): void {
    me.value = value
    status.value = 'signed_in'
  }

  function setSignedOut(): void {
    me.value = null
    status.value = 'signed_out'
  }

  // A signed-in person with no account cannot use the app. Their session
  // or token is ended and the login screen explains why.
  async function signOutWithoutAccount(): Promise<void> {
    try {
      await auth.signOut()
    } catch {
      // The local state is cleared regardless.
    }

    setSignedOut()
    notice.value = 'account.no_membership'
  }

  // Called once, before the first navigation. Whatever happens, the app
  // must still load, so nothing here throws.
  async function bootstrap(): Promise<void> {
    if (isNative && tokenStorage.get() === null) {
      setSignedOut()

      return
    }

    try {
      setSignedIn(await auth.fetchMe())
    } catch (error) {
      if (error instanceof ApiError && error.status === 403) {
        await signOutWithoutAccount()

        return
      }

      setSignedOut()
    }
  }

  async function signIn(email: string, password: string, remember: boolean): Promise<void> {
    try {
      setSignedIn(await auth.signIn(email, password, remember))
    } catch (error) {
      // On web, Fortify's /login accepts a user who belongs to no account
      // and the following /api/me answers 403. The token endpoint answers
      // 403 directly. Either way, end what was started.
      if (error instanceof ApiError && error.status === 403) {
        await signOutWithoutAccount()
      }

      throw error
    }
  }

  async function register(fields: RegisterFields): Promise<void> {
    setSignedIn(await auth.register(fields))
  }

  // Never throws: whatever the API said, this device is signed out.
  async function signOut(): Promise<void> {
    try {
      await auth.signOut()
    } catch {
      // The session or token may already be gone, which is the outcome
      // wanted anyway.
    }

    setSignedOut()
    notice.value = 'auth.signed_out'
  }

  async function refresh(): Promise<void> {
    setSignedIn(await auth.fetchMe())
  }

  // Passed straight through, so that screens only ever talk to the store.
  const forgotPassword = auth.forgotPassword
  const resetPassword = auth.resetPassword
  const resendVerification = auth.resendVerification
  const checkUsername = auth.checkUsername

  function takeNotice(): string | null {
    const value = notice.value

    notice.value = null

    return value
  }

  return {
    me,
    status,
    notice,
    isAuthenticated,
    bootstrap,
    signIn,
    register,
    signOut,
    refresh,
    forgotPassword,
    resetPassword,
    resendVerification,
    checkUsername,
    takeNotice,
  }
})
