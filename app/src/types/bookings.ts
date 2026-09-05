// What GET /api/events returns, one object per event.
//
// snake_case, because that is what the API speaks and what types/auth.ts
// already mirrors. Every name below is a column in docs/database-schema.md or
// a value section 8 says is computed, so nothing here is invented on the way
// through.
//
// The unit is an EVENT, not a booking. A booking is one record at a stage and
// its dates live in a separate table, normally two rows, a trial and the main
// day (schema 5.9). So a row in the list and a mark on a day are both an
// event carrying its booking's stage, client and total, and one client with a
// trial in March and a wedding in June is two of these.

// events.type, the full check constraint from schema 5.9. The wedding day is
// `main`: there is no `wedding` value and never was.
export type EventType =
  | 'main'
  | 'trial'
  | 'consultation'
  | 'shoot'
  | 'setup'
  | 'delivery'
  | 'collection'
  | 'other'

// bookings.stage, the full check constraint from schema 5.8. Enquiries are the
// first four, bookings are the next five, and `lost` is archived.
export type BookingStage =
  | 'new'
  | 'in_conversation'
  | 'possible'
  | 'quoted'
  | 'provisional'
  | 'confirmed'
  | 'completed'
  | 'closed'
  | 'lost'
  | 'cancelled'

// Axis two of the booking lifecycle, business logic section 6. Every value
// names the party being waited on, because that is what decides the wording
// and whose problem it is. Null is nothing outstanding.
//
// It is computed and never stored (schema section 8), and the calculation
// reads the artist's enabled features first: with invoicing switched off,
// nothing is ever waiting on a deposit. So nothing here may assume all eight
// are reachable for a given account.
export type WaitingOn =
  | 'client_form'
  | 'artist_review'
  | 'artist_price'
  | 'client_signature'
  | 'client_deposit'
  | 'client_balance'
  | 'artist_not_held'
  | 'artist_enquiry_cold'

export interface BookingEvent {
  // events.id and events.booking_id. Two events can share a booking.
  id: number
  booking_id: number

  type: EventType
  // events.label: a custom display name. When it is null the type's own name
  // from bookings.event_type.* is used instead.
  label: string | null

  // events.event_date. A plain local calendar date, 'YYYY-MM-DD', never a
  // timestamp and never a Date. Schema 5.9 stores local wall clock plus an
  // IANA zone, so every timezone question stays on the server.
  date: string
  // events.start_time as 'HH:mm', or null when no call time is agreed yet,
  // which is normal on an enquiry.
  start_time: string | null

  // events.venue_name, the occasion's venue when it differs from the address
  // the work happens at, and events.city. Either can be absent on an enquiry.
  venue_name: string | null
  city: string | null

  // contacts.first_name and last_name, composed by the API.
  client_name: string

  stage: BookingStage

  // The booking total. Money is a bigint in the currency's ISO 4217 minor
  // unit beside its currency (decision 77), so this is pence for GBP and it
  // is never a float and never a formatted string. Computed from
  // booking_lines by the API (schema section 8).
  total_minor: number
  // bookings.currency, ISO 4217.
  currency: string

  waiting_on: WaitingOn | null

  // bookings.last_touched_at, a UTC instant. The "touched N days ago" figure
  // is derived from this at render with differenceInCalendarDays against the
  // local calendar date, rather than being sent as a number that goes stale
  // in an open tab.
  last_touched_at: string
}
