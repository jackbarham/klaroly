import { computed, ref } from 'vue'
import { defineStore } from 'pinia'

export interface AuthenticatedUser {
  id: number
  name: string
  email: string
}

// Placeholder. Holds who is signed in so the router guard has something to
// check. Signing in against the API is not built yet, so this stays null.
export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthenticatedUser | null>(null)

  const isAuthenticated = computed(() => user.value !== null)

  return { user, isAuthenticated }
})
