import { describe, expect, it } from 'vitest'
import { element } from '@/lib/testHelpers'
import { mountWithCleanup } from '@/lib/testMount'
import ContactDetail from '@/components/contacts/ContactDetail.vue'
import type { Contact, ContactBooking } from '@/types/contacts'

// The card, and the one state no fixture could ever produce.
//
// src/lib/contactFixtures.ts gave every booking a date, because it derived
// next_booking and last_booking from the same dated array. The endpoint does
// not: App\Services\ContactActivity::occasions() maps every booking through
// mainEvent(), which is null when the booking has no events at all, and
// ContactBookingResource sends event_type and date as `$event?->...` for
// exactly that case. An enquiry that arrived before anybody named a day is a
// real row, and it reaches this card the moment the screen is on real data.

const mount = mountWithCleanup()

function booking(over: Partial<ContactBooking> = {}): ContactBooking {
  return {
    id: 101,
    event_type: 'main',
    date: '2026-09-12',
    venue_name: 'Ashgrove Manor',
    city: 'Hertford',
    stage: 'confirmed',
    total_minor: 96000,
    currency: 'GBP',
    ...over,
  }
}

function contact(over: Partial<Contact> = {}): Contact {
  const bookings = over.bookings ?? [booking()]

  return {
    id: 1,
    first_name: 'Imogen',
    last_name: 'Hartwell',
    email: 'imogen.hartwell@example.com',
    phone: '07700 900461',
    address_line_1: '14 Sallow Rise',
    address_line_2: null,
    city: 'Hertford',
    postcode: 'SG14 1QD',
    country: 'GB',
    booking_count: bookings.length,
    next_booking: null,
    last_booking: null,
    outstanding: [],
    ...over,
    bookings,
  }
}

function show(over: Partial<Contact> = {}) {
  return mount(ContactDetail, '/contacts/1', { contact: contact(over) })
}

// The lines of the bookings list, as a reader sees them.
function bookingLines(host: HTMLElement): string[] {
  return [...host.querySelectorAll('.booking-line__lead')].map((line) => line.textContent?.trim() ?? '')
}

describe('a booking with no events', () => {
  // The crash, named as the thing it is. Undated, the old card threw inside
  // its own render on b.date.localeCompare and took the whole card with it,
  // so this asserts the card is on the screen at all before asserting what it
  // says.
  it('draws the card rather than throwing', async () => {
    const { host } = await show({ bookings: [booking({ event_type: null, date: null })] })

    expect(element(host, 'h2').textContent).toContain('Imogen Hartwell')
    expect(bookingLines(host)).toHaveLength(1)
  })

  it('says there is no date yet, rather than an event type it does not have', async () => {
    const { host } = await show({ bookings: [booking({ event_type: null, date: null })] })

    expect(bookingLines(host)[0]).toBe('No date yet')
  })

  // Paired with the assertion above, so "it says no date yet" cannot pass by
  // saying that about everything: a dated booking still reads as its type and
  // its day.
  it('still writes the type and the day for a booking that has one', async () => {
    const { host } = await show()

    expect(bookingLines(host)[0]).toBe('Wedding day, 12 September 2026')
  })

  // The API's own rule, in App\Services\ContactActivity::occasions(): "A
  // booking with no event sorts to the end, because an empty string is below
  // every date." The card sorts newest first and has to agree with it.
  it('sorts below every dated booking rather than to the top', async () => {
    const { host } = await show({
      bookings: [
        booking({ id: 101, event_type: null, date: null }),
        booking({ id: 102, date: '2026-03-01' }),
        booking({ id: 103, date: '2026-11-20' }),
      ],
    })

    expect(bookingLines(host)).toEqual([
      'Wedding day, 20 November 2026',
      'Wedding day, 1 March 2026',
      'No date yet',
    ])
  })
})
