// The shapes the API returns for the signed-in person. GET /api/me wraps
// this in {data}; the token and mobile register endpoints put it at the top
// level under "me". One Me type covers all three.

export type FeatureKey =
  | 'enquiries'
  | 'intake_forms'
  | 'agreements'
  | 'invoicing'
  | 'payment_tracking'
  | 'automation'
  | 'travel_estimates'
  | 'photos'
  | 'feedback_requests'

export type FeatureMap = Record<FeatureKey, boolean>

export interface User {
  id: number
  uuid: string
  name: string
  email: string
  email_verified_at: string | null
  // The API sends an empty array when the stored map is empty. It is
  // normalised to an object before it reaches the rest of the app.
  notification_preferences: Record<string, unknown>
  marketing_consent_at: string | null
}

export interface Account {
  id: number
  name: string
  username: string
  vertical: string
  country: string
  locale: string
  currency: string
  timezone: string
  profile_enabled: boolean
  trial_ends_at: string | null
}

export interface Membership {
  role: string
  can_edit: boolean
  can_see_prices: boolean
  can_see_invoices: boolean
  can_see_contacts: boolean
}

export interface Me {
  user: User
  account: Account
  membership: Membership
  features: FeatureMap
}

// One personal access token, as the devices screen lists them. The plain
// text token is never here; the API shows it once, when it is issued.
//
// A web session is not a token, so a browser sees only the phones and tablets
// that have signed in, and "current" is false on every row it is shown.
export interface Device {
  id: number
  name: string
  last_used_at: string | null
  expires_at: string | null
  created_at: string
  current: boolean
}

export interface RegisterFields {
  business_name: string
  name: string
  email: string
  password: string
  username?: string
  marketing_consent: boolean
}

export type UsernameReason = 'invalid' | 'reserved' | 'taken' | null

export interface UsernameCheck {
  available: boolean
  reason: UsernameReason
}
