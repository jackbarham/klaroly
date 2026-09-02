import type { Me } from '@/types/auth'

// A Me payload as the API returns it, for tests only. notification_preferences
// is the empty array the API sends for an empty map.
export const sampleMe = {
  user: {
    id: 1,
    uuid: 'u',
    name: 'Ellie Marsh',
    email: 'ellie@example.com',
    email_verified_at: null,
    notification_preferences: [],
    marketing_consent_at: null,
  },
  account: {
    id: 1,
    name: 'Ellie Marsh Makeup',
    username: 'elliemarshmakeup',
    vertical: 'wedding_makeup',
    country: 'GB',
    locale: 'en-GB',
    currency: 'GBP',
    timezone: 'Europe/London',
    profile_enabled: false,
    trial_ends_at: null,
  },
  membership: {
    role: 'owner',
    can_edit: true,
    can_see_prices: true,
    can_see_invoices: true,
    can_see_contacts: true,
  },
  features: {
    enquiries: true,
    intake_forms: false,
    agreements: true,
    invoicing: true,
    payment_tracking: true,
    automation: false,
    travel_estimates: false,
    photos: false,
    feedback_requests: false,
  },
} as unknown as Me
