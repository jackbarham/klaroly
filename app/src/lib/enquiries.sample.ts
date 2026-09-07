import type { FeatureMap } from '@/types/auth'
import type { Enquiry } from '@/types/enquiries'

// An enquiry as the API returns it, for tests only, the way auth.sample.ts
// carries a Me. Five test files built the same record field by field, so a
// field added to the type was five edits; now it is one.
export function sampleEnquiry(over: Partial<Enquiry> = {}): Enquiry {
  return {
    id: 1,
    stage: 'possible',
    client_name: 'Imogen Hartwell',
    contact_id: 10,
    source: 'web_form',
    source_booking: null,
    last_touched_at: new Date(2026, 8, 3, 12).toISOString(),
    waiting_on: null,
    total_minor: null,
    currency: 'GBP',
    event: {
      type: 'main',
      date: '2027-05-29',
      location_type: 'venue',
      venue_name: 'Marlbrook Hall',
      city: 'Ludworth',
    },
    has_trial: false,
    lost_reason: null,
    lost_side: null,
    clash: null,
    ...over,
  }
}

// Every feature on, for a test about the stage rule rather than the feature
// rule.
export const allFeaturesOn: FeatureMap = {
  enquiries: true,
  intake_forms: true,
  agreements: true,
  invoicing: true,
  payment_tracking: true,
  automation: true,
  travel_estimates: true,
  photos: true,
  feedback_requests: true,
}
