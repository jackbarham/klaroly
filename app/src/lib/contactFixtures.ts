import { addDays, startOfDay } from 'date-fns'
import { dayKey } from '@/lib/monthGrid'
import type { Contact, ContactBooking, OutstandingAmount } from '@/types/contacts'

// TEMPORARY. This file goes when the contacts endpoint lands.
//
// There is no contacts API yet, so this stands in for one: loadContacts() is
// the seam, src/stores/contacts.ts is its only caller, and nothing under
// src/components may import this file. src/lib/contacts.guards.test.ts checks
// that last part by reading the source, because the point of the rule is what
// happens when nobody is looking.
//
// When the API arrives, src/lib/contacts.ts is written the way
// src/lib/bookings.ts is written, the store calls that instead of this, and
// this file is deleted. Nothing else in the feature changes: every component
// already reads the store and every shape already matches the payload.
//
// Every person, venue and address below is invented. That is not politeness:
// this screen is what gets screenshotted, and a real venue's name in a
// screenshot is somebody else's business in our marketing. The demo seeder in
// the API was swept for exactly this and a real venue had survived inside an
// enquiry message, so the rule is checked against the strings rather than
// against the columns you expect them in.

// Dates are relative to today rather than written down, so the fixtures do not
// go stale: "four contacts with a future booking" has to still be true next
// spring. dayKey is imported rather than the format string being written
// again, so this file cannot be the second place a day key is built.
const today = startOfDay(new Date())

function on(days: number): string {
  return dayKey(addDays(today, days))
}

function booking(
  id: number,
  eventType: ContactBooking['event_type'],
  days: number,
  venue: string | null,
  city: string | null,
  stage: ContactBooking['stage'],
  total: number,
): ContactBooking {
  return {
    id,
    event_type: eventType,
    date: on(days),
    venue_name: venue,
    city,
    stage,
    total_minor: total,
    currency: 'GBP',
  }
}

function owed(minor: number, overdue: boolean): OutstandingAmount[] {
  return [{ currency: 'GBP', minor, overdue }]
}

// The four computed fields, worked out here so that each fixture below reads
// as a person rather than as a payload. The API will compute the same four in
// SQL; this is the only place in the app that derives them, and it is the
// place that is about to be deleted.
function withDerived(
  contact: Omit<Contact, 'booking_count' | 'next_booking' | 'last_booking'>,
): Contact {
  const key = dayKey(today)
  const byDate = [...contact.bookings].sort((a, b) => a.date.localeCompare(b.date))

  return {
    ...contact,
    booking_count: contact.bookings.length,
    next_booking: byDate.find((entry) => entry.date >= key) ?? null,
    last_booking: [...byDate].reverse().find((entry) => entry.date < key) ?? null,
  }
}

const people: Omit<Contact, 'booking_count' | 'next_booking' | 'last_booking'>[] = [
  {
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
    bookings: [
      booking(101, 'main', 84, 'Ashgrove Manor, Little Hadham', 'Hertford', 'confirmed', 96000),
      booking(102, 'trial', 42, null, 'Hertford', 'confirmed', 12000),
    ],
    outstanding: owed(45000, false),
  },
  {
    id: 2,
    first_name: 'Nadia',
    last_name: 'Okonkwo',
    email: 'nadia.okonkwo@example.com',
    phone: '07700 900118',
    address_line_1: 'Flat 6, Weaver House',
    address_line_2: '3 Cobbold Street',
    city: 'Manchester',
    postcode: 'M4 5JG',
    country: 'GB',
    bookings: [
      booking(103, 'main', 21, 'The Fernbank Rooms', 'Manchester', 'confirmed', 74000),
    ],
    outstanding: [],
  },
  {
    id: 3,
    first_name: 'Charlotte',
    last_name: 'Brontë-Vance',
    email: 'charlotte.bv@example.com',
    phone: '07700 900733',
    address_line_1: '2 Quillgate Lane',
    address_line_2: null,
    city: 'Halifax',
    postcode: 'HX1 2AB',
    country: 'GB',
    bookings: [
      booking(104, 'main', 160, 'Corvel Hall', 'Halifax', 'provisional', 88000),
    ],
    outstanding: [],
  },
  {
    id: 4,
    first_name: 'Priya',
    last_name: 'Raman',
    email: 'priya.raman@example.com',
    phone: '07700 900204',
    address_line_1: '77 Larkspur Avenue',
    address_line_2: null,
    city: 'Leicester',
    postcode: 'LE2 3NH',
    country: 'GB',
    bookings: [
      booking(105, 'main', 7, 'The Copper Yard', 'Leicester', 'confirmed', 132000),
      booking(106, 'trial', -14, null, 'Leicester', 'completed', 12000),
    ],
    outstanding: owed(66000, false),
  },
  // The overdue one. Last summer's wedding, still not settled.
  {
    id: 5,
    first_name: 'Georgia',
    last_name: 'Pellow',
    email: 'georgia.pellow@example.com',
    phone: '07700 900855',
    address_line_1: '9 Tanner\'s Walk',
    address_line_2: null,
    city: 'Truro',
    postcode: 'TR1 2QW',
    country: 'GB',
    bookings: [
      booking(107, 'main', -96, 'Westerly Mill', 'Truro', 'completed', 81000),
    ],
    outstanding: owed(28000, true),
  },
  // One name and nothing else. She books under it and always has.
  {
    id: 6,
    first_name: 'Anouk',
    last_name: null,
    email: 'anouk@example.com',
    phone: '07700 900377',
    address_line_1: null,
    address_line_2: null,
    city: 'Brighton',
    postcode: null,
    country: 'GB',
    bookings: [
      booking(108, 'shoot', -30, 'The Wren Rooms', 'Brighton', 'completed', 45000),
    ],
    outstanding: [],
  },
  // Two weddings, two sisters, one mother paying for both.
  {
    id: 7,
    first_name: 'Bernadette',
    last_name: 'Muirhead',
    email: 'b.muirhead@example.com',
    phone: '07700 900940',
    address_line_1: 'Stonecrop Farm',
    address_line_2: 'Ganthorpe Road',
    city: 'York',
    postcode: 'YO60 7HL',
    country: 'GB',
    bookings: [
      booking(109, 'main', 240, 'Bramblewick Hall', 'York', 'provisional', 104000),
      booking(110, 'main', -420, 'Bramblewick Hall', 'York', 'completed', 98000),
    ],
    outstanding: [],
  },
  {
    id: 8,
    first_name: 'Rosalind',
    last_name: 'Muirhead',
    email: 'rosalind.muirhead@example.com',
    phone: '07700 900941',
    address_line_1: '31 Ganthorpe Road',
    address_line_2: null,
    city: 'York',
    postcode: 'YO60 7HN',
    country: 'GB',
    bookings: [
      booking(111, 'trial', -400, null, 'York', 'completed', 11000),
    ],
    outstanding: [],
  },
  // Enquired, went quiet, never became a booking.
  {
    id: 9,
    first_name: 'Tamsin',
    last_name: 'Ely',
    email: 'tamsin.ely@example.com',
    phone: '07700 900512',
    address_line_1: null,
    address_line_2: null,
    city: 'Norwich',
    postcode: null,
    country: 'GB',
    bookings: [
      booking(112, 'main', -230, 'The Mallow Barn', 'Norwich', 'lost', 0),
    ],
    outstanding: [],
  },
  // Typed in from a card at a wedding fair. Nothing against her yet.
  {
    id: 10,
    first_name: 'Fenella',
    last_name: 'Ashworth',
    email: 'fenella.ashworth@example.com',
    phone: '07700 900066',
    address_line_1: null,
    address_line_2: null,
    city: 'Chester',
    postcode: null,
    country: 'GB',
    bookings: [],
    outstanding: [],
  },
  {
    id: 11,
    first_name: 'Marguerite',
    last_name: 'Devaux',
    email: 'm.devaux@example.com',
    phone: '07700 900288',
    address_line_1: '5 Pennyfield Court',
    address_line_2: null,
    city: 'Bath',
    postcode: 'BA1 5TG',
    country: 'GB',
    bookings: [
      booking(113, 'main', -140, 'Pennyfield Hall', 'Bath', 'completed', 118000),
    ],
    outstanding: [],
  },
  {
    id: 12,
    first_name: 'Sian',
    last_name: 'Prothero',
    email: 'sian.prothero@example.com',
    phone: '07700 900649',
    address_line_1: '12 Hartleap Close',
    address_line_2: null,
    city: 'Cardiff',
    postcode: 'CF14 3RS',
    country: 'GB',
    bookings: [
      booking(114, 'main', -310, 'Hartleap Barn', 'Cardiff', 'completed', 92000),
    ],
    outstanding: [],
  },
  {
    id: 13,
    first_name: 'Adaeze',
    last_name: 'Nwosu',
    email: 'adaeze.nwosu@example.com',
    phone: '07700 900174',
    address_line_1: '48 Marlow Hollow',
    address_line_2: null,
    city: 'Birmingham',
    postcode: 'B15 2TT',
    country: 'GB',
    bookings: [
      booking(115, 'main', -505, 'The Old Cordwainery', 'Birmingham', 'completed', 87000),
      booking(116, 'trial', -540, null, 'Birmingham', 'completed', 11000),
    ],
    outstanding: [],
  },
  {
    id: 14,
    first_name: 'Verity',
    last_name: 'Calloway',
    email: 'verity.calloway@example.com',
    phone: '07700 900823',
    address_line_1: 'The Coach House',
    address_line_2: 'Ivythwaite Lane',
    city: 'Kendal',
    postcode: 'LA9 4RT',
    country: 'GB',
    bookings: [
      booking(117, 'main', -620, 'Ivythwaite Lodge', 'Kendal', 'completed', 79000),
    ],
    outstanding: [],
  },
  {
    id: 15,
    first_name: 'Orla',
    last_name: 'Kinsella',
    email: 'orla.kinsella@example.com',
    phone: '07700 900395',
    address_line_1: '3 Sallow Court',
    address_line_2: null,
    city: 'Liverpool',
    postcode: 'L1 9BG',
    country: 'GB',
    bookings: [
      booking(118, 'main', -700, 'Sallow Court', 'Liverpool', 'completed', 101000),
    ],
    outstanding: [],
  },
  {
    id: 16,
    first_name: 'Delphine',
    last_name: null,
    email: 'delphine@example.com',
    phone: '07700 900910',
    address_line_1: null,
    address_line_2: null,
    city: 'London',
    postcode: null,
    country: 'GB',
    bookings: [
      booking(119, 'shoot', -760, 'The Larkspur Rooms', 'London', 'completed', 52000),
    ],
    outstanding: [],
  },
  {
    id: 17,
    first_name: 'Harriet',
    last_name: 'Stubbings',
    email: 'harriet.stubbings@example.com',
    phone: '07700 900447',
    address_line_1: '21 Copper Row',
    address_line_2: null,
    city: 'Sheffield',
    postcode: 'S1 2GU',
    country: 'GB',
    bookings: [
      booking(120, 'main', -820, 'Quillgate House', 'Sheffield', 'completed', 84000),
    ],
    outstanding: [],
  },
  {
    id: 18,
    first_name: 'Mei-Ling',
    last_name: 'Chau',
    email: 'meiling.chau@example.com',
    phone: '07700 900531',
    address_line_1: '6 Wren Gardens',
    address_line_2: null,
    city: 'Reading',
    postcode: 'RG1 4QP',
    country: 'GB',
    bookings: [
      booking(121, 'main', -910, 'Nine Acre Barn', 'Reading', 'completed', 76000),
    ],
    outstanding: [],
  },
  {
    id: 19,
    first_name: 'Constance',
    last_name: 'Farrier',
    email: 'constance.farrier@example.com',
    phone: '07700 900682',
    address_line_1: 'Rookery Cottage',
    address_line_2: 'Bramblewick Road',
    city: 'Durham',
    postcode: 'DH1 3LE',
    country: 'GB',
    bookings: [
      booking(122, 'main', -1010, 'The Tanner\'s Loft', 'Durham', 'completed', 69000),
    ],
    outstanding: [],
  },
  {
    id: 20,
    first_name: 'Zainab',
    last_name: 'Al-Rashid',
    email: 'zainab.alrashid@example.com',
    phone: '07700 900259',
    address_line_1: '90 Corvel Street',
    address_line_2: null,
    city: 'Glasgow',
    postcode: 'G1 3SL',
    country: 'GB',
    bookings: [
      booking(123, 'main', -1130, 'Westerly Mill', 'Glasgow', 'completed', 94000),
    ],
    outstanding: [],
  },
  {
    id: 21,
    first_name: 'Eloise',
    last_name: 'Trenchard',
    email: 'eloise.trenchard@example.com',
    phone: '07700 900718',
    address_line_1: '4 Mallow Street',
    address_line_2: null,
    city: 'Exeter',
    postcode: 'EX4 3PQ',
    country: 'GB',
    bookings: [
      booking(124, 'main', -1290, 'Stonecrop Farm', 'Exeter', 'completed', 71000),
    ],
    outstanding: [],
  },
  {
    id: 22,
    first_name: 'Beatrix',
    last_name: 'Ogilvy',
    email: 'beatrix.ogilvy@example.com',
    phone: '07700 900604',
    address_line_1: '18 Fernbank Terrace',
    address_line_2: null,
    city: 'Edinburgh',
    postcode: 'EH3 6QA',
    country: 'GB',
    bookings: [
      booking(125, 'main', -1400, 'Corvel Hall', 'Edinburgh', 'completed', 89000),
    ],
    outstanding: [],
  },
]

// Async, because the thing replacing it is a request. A caller that already
// awaits cannot tell the difference on the day this goes.
export function loadContacts(): Promise<Contact[]> {
  return Promise.resolve(people.map(withDerived))
}
