import { api } from '@/lib/api'
import type { BookingEvent } from '@/types/bookings'

// The bookings screen's two reads. It sits where src/lib/auth.ts sits: below
// the store, above src/lib/api.ts, and it is the only file besides auth.ts
// that turns a URL into a domain shape. Screens never import it; they go
// through src/stores/bookings.ts, and src/lib/boundary.test.ts fails if one
// tries.
//
// Both endpoints wrap their payload in `data`, as /api/me does, and both
// unwrap it here so that nothing above this line knows the envelope exists.

export interface EventWindow {
  // 'YYYY-MM-DD'. Omitted means today, which is the API's own default.
  from?: string
  // Omitted means no upper bound, which is what the first call wants: the
  // list groups upcoming work into four bands and "later" cannot be computed
  // from a subset.
  to?: string
}

export async function events(params: EventWindow = {}): Promise<BookingEvent[]> {
  const query = new URLSearchParams()

  if (params.from !== undefined) {
    query.set('from', params.from)
  }

  if (params.to !== undefined) {
    query.set('to', params.to)
  }

  const suffix = query.size > 0 ? `?${query}` : ''
  const response = await api.get<{ data: BookingEvent[] }>(`/api/events${suffix}`)

  return response.data
}

// Every month the account holds an event in, for all time, as 'YYYY-MM'.
//
// This is the jump sheet's dots and the bounds of its year strip. It takes no
// parameters and is a few hundred bytes even for an artist ten years in, which
// is why it is a second call rather than something derived from the windowed
// one: a derivation would only ever know about the months already loaded.
export async function eventMonths(): Promise<string[]> {
  const response = await api.get<{ data: string[] }>('/api/events/months')

  return response.data
}
