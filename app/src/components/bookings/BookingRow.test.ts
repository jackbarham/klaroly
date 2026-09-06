import { describe, expect, it } from 'vitest'
import { element } from '@/lib/testHelpers'
import { mountWithCleanup } from '@/lib/testMount'
import BookingRow from '@/components/bookings/BookingRow.vue'
import type { BookingEvent, LocationType } from '@/types/bookings'

// Where the row says the work happens.
//
// This exists because the rule is the whole of the change: reading the venue
// columns alone said "Venue not given" on every trial, when the venue was not
// unknown at all, it was the artist's own place and its address lives in
// settings. Each case below is one the API can actually send.

const mount = mountWithCleanup()

function event(over: Partial<BookingEvent> = {}): BookingEvent {
  return {
    id: 1,
    booking_id: 1,
    type: 'main',
    label: null,
    date: '2026-09-26',
    start_time: '06:30',
    location_type: 'venue',
    venue_name: 'Cathermere Barn',
    city: 'Berkeley',
    client_name: 'Priya Raman',
    stage: 'confirmed',
    total_minor: 78000,
    currency: 'GBP',
    waiting_on: null,
    last_touched_at: '2026-09-01T09:00:00.000000Z',
    ...over,
  }
}

async function meta(over: Partial<BookingEvent>): Promise<string> {
  const { host } = await mount(BookingRow, '/bookings', { event: event(over) })

  return element(host, '.text-meta').textContent?.trim() ?? ''
}

describe('the line that says where', () => {
  it('names the venue when there is one', async () => {
    expect(await meta({ location_type: 'venue' })).toContain('Cathermere Barn')
  })

  it('falls back to the town when a venue has no name yet', async () => {
    expect(await meta({ location_type: 'venue', venue_name: null })).toContain('Berkeley')
  })

  // The case that started this. A trial at the artist's own place has a null
  // venue and a null city because the base address is in settings, and it was
  // reading as a wedding whose venue nobody had given.
  it('says the artist\'s own place rather than calling the venue missing', async () => {
    const line = await meta({ location_type: 'base', type: 'trial', venue_name: null, city: null })

    expect(line).toContain('Your place')
    expect(line).not.toContain('Venue not given')
  })

  it('names the client\'s town when the work is at theirs', async () => {
    expect(await meta({ location_type: 'client', venue_name: null, city: 'Bath' })).toContain('Bath')
  })

  it('says their place when the work is at theirs and the town is not known', async () => {
    const line = await meta({ location_type: 'client', venue_name: null, city: null })

    expect(line).toContain('Their place')
    expect(line).not.toContain('Venue not given')
  })

  // Paired with the assertions above, so that neither can quietly stop being
  // about anything: "Venue not given" has to still appear where it belongs, or
  // the ones asserting its absence prove nothing.
  it('still says the venue is not given when nobody has said where', async () => {
    expect(await meta({ location_type: null, venue_name: null, city: null }))
      .toContain('Venue not given')
  })

  it('says the venue is not given for a venue nobody has named or placed', async () => {
    expect(await meta({ location_type: 'venue', venue_name: null, city: null }))
      .toContain('Venue not given')
  })

  it.each<LocationType | null>(['base', 'client', 'venue', null])(
    'still shows the date and the time for a %s event',
    async (location) => {
      const line = await meta({ location_type: location })

      expect(line).toContain('Sat 26 Sep')
      expect(line).toContain('06:30')
    },
  )
})
