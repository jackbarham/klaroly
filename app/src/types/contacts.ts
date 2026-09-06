// What the contacts endpoint will return, one object per contact.
//
// snake_case, because that is what the API speaks and what types/auth.ts and
// types/bookings.ts already mirror. Every name below is a column in
// docs/database-schema.md or a value section 8 says is computed, so nothing
// here is invented on the way through.
//
// A contact is the person who books and pays (schema 5.7): a name, one email,
// ONE phone number and an address. Everyone else on the day is a party member
// or a booking contact, and neither appears on this screen.

import type { BookingStage, EventType } from '@/types/bookings'

// One of the contact's bookings, flattened with its own main-or-trial date.
//
// EventType and BookingStage are imported rather than declared again: the
// wedding day is `main` and there is no `wedding` value, here or anywhere.
export interface ContactBooking {
  // bookings.id, so a row links to the booking rather than to the event.
  id: number

  // events.type.
  event_type: EventType
  // events.event_date. A plain local calendar date, 'YYYY-MM-DD', never a
  // timestamp and never a Date, so every timezone question stays on the
  // server (schema 5.9 stores local wall clock plus an IANA zone).
  date: string

  // events.venue_name and events.city. Either can be absent on an enquiry.
  venue_name: string | null
  city: string | null

  stage: BookingStage

  // The booking total. Money is a bigint in the currency's ISO 4217 minor
  // unit beside its currency (decision 77), so this is pence for GBP and it is
  // never a float and never a formatted string. Computed from booking_lines by
  // the API (schema section 8).
  total_minor: number
  currency: string
}

// What this contact still owes, in one currency.
//
// An array of these rather than a single `outstanding_minor` beside a single
// `currency`, because schema section 8 is explicit that money is grouped by
// currency and never summed across it. An artist who has worked one job abroad
// has a contact whose outstanding balance cannot be written as one number, and
// the flat version of this shape had no way to say so: it would have reported
// a figure in the wrong currency while looking perfectly correct.
//
// `overdue` belongs to the amount rather than to the person for the same kind
// of reason: a contact can carry an overdue balance on last June's wedding and
// a deposit that is not due until the spring.
export interface OutstandingAmount {
  currency: string
  // Always greater than zero. Nothing owed is an empty array, not a zero.
  minor: number
  // Computed from the invoice's balance_due_on and deposit_due_on against
  // today, respecting reminders_snoozed_until (schema section 8).
  overdue: boolean
}

export interface Contact {
  id: number

  first_name: string
  // NULLABLE in schema 5.7, and every piece of sorting, grouping and initials
  // code in this feature has to cope with it. Somebody with one name is filed
  // under that name, never under a dash.
  last_name: string | null

  email: string | null
  // One number. The schema holds one and this holds one.
  phone: string | null

  address_line_1: string | null
  address_line_2: string | null
  city: string | null
  postcode: string | null
  // contacts.country, ISO 3166-1 alpha-2. It goes into the maps query and is
  // not drawn as a line of the address: on a UK-only account it would put "GB"
  // under every address on the screen.
  country: string | null

  // Every booking this contact has ever had, newest first. The detail draws
  // this; the list never touches it, which is what would let a future list
  // payload leave it out entirely.
  bookings: ContactBooking[]

  // The four values below are computed by the API and arrive on the payload.
  // None of them is a column and none of them is derived in a component: they
  // are here so that the list can sort, group and label a row without holding
  // every booking of every contact.
  //
  // next_booking is the soonest booking on or after today, or null. last_booking
  // is the most recent one before today, or null. A contact with no bookings at
  // all has null for both and a count of zero.
  booking_count: number
  next_booking: ContactBooking | null
  last_booking: ContactBooking | null

  outstanding: OutstandingAmount[]
}
