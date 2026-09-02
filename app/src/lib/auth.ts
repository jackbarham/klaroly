import { api } from '@/lib/api'
import { deviceName, isNative } from '@/lib/platform'
import * as tokenStorage from '@/lib/tokenStorage'
import type { Me, RegisterFields, UsernameCheck } from '@/types/auth'

// The one module that knows the web app signs in with a session cookie and
// the native app signs in with a bearer token. Besides api.ts it is the only
// file that may branch on the platform. Screens never import this directly;
// they call the auth store, and the store calls these functions.
//
// Every function returns typed data from src/types/auth.ts and throws
// ApiError when the API says no.

interface TokenResponse {
  token: string
  expires_at: string
  me: Me
}

interface MessageResponse {
  message: string
}

export async function signIn(email: string, password: string, remember: boolean): Promise<Me> {
  if (isNative) {
    const response = await api.post<TokenResponse>('/api/auth/token', {
      email,
      password,
      device_name: deviceName(),
    })

    tokenStorage.set(response.token)

    return normaliseMe(response.me)
  }

  await api.post('/login', { email, password, remember })

  return fetchMe()
}

export async function register(fields: RegisterFields): Promise<Me> {
  // The API requires password_confirmation. The screen has one password
  // field with a show-password toggle (decision 88), so the confirmation is
  // the password itself.
  const body: Record<string, unknown> = {
    business_name: fields.business_name,
    name: fields.name,
    email: fields.email,
    password: fields.password,
    password_confirmation: fields.password,
    marketing_consent: fields.marketing_consent,
  }

  // Sent only when given, so an empty field leaves the API to derive one.
  if (fields.username) {
    body.username = fields.username.toLowerCase()
  }

  if (isNative) {
    const response = await api.post<TokenResponse>('/api/auth/register', {
      ...body,
      device_name: deviceName(),
    })

    tokenStorage.set(response.token)

    return normaliseMe(response.me)
  }

  await api.post('/register', body)

  return fetchMe()
}

// Local state is cleared whether or not the API accepted the request: a
// person who asked to sign out is signed out as far as this device is
// concerned.
export async function signOut(): Promise<void> {
  try {
    if (isNative) {
      await api.delete('/api/auth/token')
    } else {
      await api.post('/logout')
    }
  } finally {
    if (isNative) {
      tokenStorage.clear()
    }
  }
}

export async function fetchMe(): Promise<Me> {
  const response = await api.get<{ data: Me }>('/api/me')

  return normaliseMe(response.data)
}

export async function forgotPassword(email: string): Promise<void> {
  await api.post<MessageResponse>(isNative ? '/api/auth/forgot-password' : '/forgot-password', { email })
}

export async function resetPassword(token: string, email: string, password: string): Promise<void> {
  await api.post<MessageResponse>(isNative ? '/api/auth/reset-password' : '/reset-password', {
    token,
    email,
    password,
    password_confirmation: password,
  })
}

// Resolves to true when an email was sent (202) and false when the address
// was already verified (204).
export async function resendVerification(): Promise<boolean> {
  const path = isNative
    ? '/api/auth/email/verification-notification'
    : '/email/verification-notification'

  const body = await api.post<unknown>(path)

  return body !== null
}

// The rule only accepts lowercase, so lowercase before asking.
export async function checkUsername(username: string): Promise<UsernameCheck> {
  return api.get<UsernameCheck>(`/api/usernames/${encodeURIComponent(username.toLowerCase())}`)
}

// The API serialises an empty notification_preferences map as an empty
// array. Every path that receives a Me comes through here so the rest of the
// app only ever sees an object.
function normaliseMe(me: Me): Me {
  const preferences = me.user.notification_preferences

  return {
    ...me,
    user: {
      ...me.user,
      notification_preferences: Array.isArray(preferences) || preferences === null ? {} : preferences,
    },
  }
}
