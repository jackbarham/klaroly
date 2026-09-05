import { ref } from 'vue'
import { defineStore } from 'pinia'
import { loadBookingEvents } from '@/lib/bookingFixtures'
import type { BookingEvent } from '@/types/bookings'

// The events the Bookings screen draws. Components read this store and never
// import the fixtures, so when the API arrives the change is the body of
// loadBookingEvents and nothing here.

export type BookingsStatus = 'idle' | 'loading' | 'ready' | 'failed'

export const useBookingsStore = defineStore('bookings', () => {
  const events = ref<BookingEvent[]>([])
  const status = ref<BookingsStatus>('idle')

  // Called when the screen mounts. It fetches once: coming back to Bookings
  // from another tab should not refetch, and two components mounting together
  // should not fetch twice.
  async function load(): Promise<void> {
    if (status.value === 'loading' || status.value === 'ready') {
      return
    }

    status.value = 'loading'

    try {
      events.value = await loadBookingEvents()
      status.value = 'ready'
    } catch {
      // The screen shows its failed state; there is nothing here to retry
      // with until the real request exists.
      events.value = []
      status.value = 'failed'
    }
  }

  return { events, status, load }
})
