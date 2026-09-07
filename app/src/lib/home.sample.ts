import type { AttentionRow, UpcomingEvent } from '@/types/home'

// The home screen's two row shapes as the API returns them, for tests only,
// the way auth.sample.ts carries a Me. Three test files built each of them
// field by field.

export function attentionRow(id: number, over: Partial<AttentionRow> = {}): AttentionRow {
  return {
    booking_id: id,
    waiting_on: 'artist_price',
    party: 'artist',
    client_name: `Client ${id}`,
    contact_id: id,
    stage: 'possible',
    currency: 'GBP',
    event_date: '2027-07-04',
    trial_date: null,
    last_touched_at: '2026-09-01T09:00:00.000000Z',
    created_at: '2026-09-01T09:00:00.000000Z',
    converted_at: null,
    sent_at: null,
    hold_expires_at: null,
    outstanding_minor: null,
    invoice_total_minor: null,
    due_on: null,
    ...over,
  }
}

export function upcomingEvent(over: Partial<UpcomingEvent> = {}): UpcomingEvent {
  return {
    event_id: 1,
    booking_id: 1,
    type: 'main',
    label: null,
    date: '2026-09-12',
    start_time: '06:30',
    location_type: 'venue',
    venue_name: 'Penbury Manor',
    city: 'Hitchin',
    client_name: 'Nadia Kerrigan',
    stage: 'confirmed',
    party_size: 5,
    travel_duration_s: null,
    travel_distance_m: null,
    ...over,
  }
}
