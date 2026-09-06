// What the two enquiries endpoints return.
//
// snake_case, because that is what the API speaks and what types/auth.ts,
// types/bookings.ts and types/contacts.ts already mirror. Every name below is
// a column in docs/database-schema.md or a value section 8 says is computed,
// so nothing here is invented on the way through.
//
// **The unit is the enquiry, not the event**, which is the one place this
// differs from types/bookings.ts and the reason there are two endpoints rather
// than a filter over one. An enquiry often has no date at all, which an
// events-shaped payload cannot represent because there would be no row; and an
// enquiry with a trial and a wedding is one conversation, where two rows would
// mean two staleness figures reading the same number.
//
// There is no enquiries table. Business logic 4.3 is one bookings table with a
// stage column, so `id` below is a BOOKING's id and an enquiry is that record
// at an early stage.

import type { BookingStage, EventType, LocationType, WaitingOn } from '@/types/bookings'

// bookings.source, the check constraint from schema 5.8. How the enquiry
// arrived, as a key: what it is called on screen is this app's decision, the
// same way a stage is.
export type EnquirySource =
  | 'manual'
  | 'web_form'
  | 'forwarded_email'
  | 'voice_note'
  | 'captured_at_event'
  | 'other'

// bookings.lost_reason, the nine values in App\Enums\LostReason.
//
// An enquiry ends two ways: the client goes elsewhere, or the artist turns the
// work down. It is a reason with a side rather than a second stage, so the
// record still says `lost` and nothing that asks "is this live" had to change.
// Two of the nine read "Another reason" on screen and that is not a
// duplication: which side a reason sits on is the fact being recorded.
export type LostReason =
  | 'went_elsewhere'
  | 'too_expensive'
  | 'wedding_off'
  | 'no_reply'
  | 'client_other'
  | 'already_booked'
  | 'too_far'
  | 'not_right_fit'
  | 'artist_other'

// Derived from the reason by the API rather than mapped here, because the side
// is a fact about the record and the label beside it is wording.
export type EndingSide = 'client' | 'artist'

// The one date the row is shown by: the main day, or the earliest event when
// there is no main one. Chosen by the API so the enquiries row and the
// contacts card cannot show one booking under two different dates.
export interface EnquiryEvent {
  type: EventType
  // A plain local calendar date, 'YYYY-MM-DD', never a timestamp and never a
  // Date. Schema 5.9 stores local wall clock plus an IANA zone, so every
  // timezone question stays on the server.
  date: string
  // Present because the venue columns cannot tell "nobody has said where" from
  // "at her own place, whose address lives in settings": both are a null venue
  // and a null city.
  location_type: LocationType | null
  venue_name: string | null
  city: string | null
}

// Enough of the booking this enquiry was captured at to say "met at Elspeth
// Rowntree's wedding". Its date is chosen the same way the row's is.
export interface SourceBooking {
  id: number
  client_name: string
  date: string | null
}

/**
 * What is ALREADY on this enquiry's date, per business logic 5.2.
 *
 * The buckets are the calendar's own, from strengthByStage in
 * src/lib/dayMarks.ts: confirmed, completed and closed are filled marks;
 * provisional is a ring; `others` counts other enquiries at possible or
 * quoted, which is the count badge. It never counts the row's own booking.
 *
 * Null when the date carries nothing else, when the enquiry has no date, and
 * when the enquiry is lost, because a lost enquiry has released the date.
 */
export interface Clash {
  confirmed: number
  provisional: number
  others: number
}

export interface Enquiry {
  // bookings.id. A row links to the booking, because that is what it is.
  id: number
  stage: BookingStage
  // contacts.first_name and last_name, composed by the API.
  client_name: string
  contact_id: number

  source: EnquirySource | null
  source_booking: SourceBooking | null

  // bookings.last_touched_at, a UTC instant. The "11 days" figure is derived
  // at render with differenceInCalendarDays against the local calendar day,
  // rather than sent as a number that goes stale in a tab left open overnight.
  last_touched_at: string

  // Computed by the API, which reads the artist's enabled features first. Its
  // artist_enquiry_cold value IS the screen's "Gone quiet" band: the threshold
  // is one number read once on the server and never reaches the client, so
  // this screen and the home screen cannot disagree about it.
  waiting_on: WaitingOn | null

  // The booking total in the currency's minor unit (decision 77), and **null
  // when nobody has priced it at all**, which is most enquiries. Zero is a
  // price somebody chose. The screen says "No price yet" for the first and
  // "£0" for the second, and neither the total nor the stage could tell them
  // apart before this was made nullable.
  total_minor: number | null
  currency: string

  // Null is a first-class value here and the row says "No date yet" rather
  // than leaving the line blank: a row that says nothing where a date goes
  // reads as a bug in the app rather than a fact about the wedding.
  event: EnquiryEvent | null

  // Whether there is a trial as well as the day above, so the row can say "and
  // a trial". It cannot be worked out from `event`, which carries the main day
  // when there is one: a booking with a trial in March and the wedding in May
  // is otherwise indistinguishable from one with no trial.
  has_trial: boolean

  lost_reason: LostReason | null
  lost_side: EndingSide | null

  clash: Clash | null
}

export interface EnquiryNote {
  id: number
  body: string
  // A UTC instant, like last_touched_at and for the same reason.
  created_at: string
}

/**
 * The second request: one enquiry opened.
 *
 * It is the list row plus the three things a list cannot afford. The message
 * in particular is a pasted WhatsApp thread, and five hundred of those is not
 * a list payload; the list is the half that has to work with no signal, and a
 * detail opened by tapping does not.
 */
export interface EnquiryDetail extends Enquiry {
  // bookings.enquiry_message. Business logic 5.5.1 keeps the source on the
  // record, which is the difference between a name from four months ago and a
  // conversation that can be picked up.
  enquiry_message: string | null
  // How many people are having something done, or null when nobody has said.
  // Null rather than zero: a party of nobody is not a thing anybody books, so
  // a zero could only mean "not known yet" wearing a number.
  party_size: number | null
  // The private stream on this booking, newest first. Booking notes only: a
  // note against the person belongs on the contact's card.
  notes: EnquiryNote[]
}

export interface EnquiryMeta {
  total: number
  returned: number
  // True when the account holds more enquiries than the endpoint's ceiling. A
  // flag rather than a refusal, because a caller that sends no parameters
  // cannot ask for less.
  truncated: boolean
}
