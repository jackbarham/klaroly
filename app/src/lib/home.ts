import { api } from '@/lib/api'
import type { Home, HomeMeta, HomeSummary } from '@/types/home'

// The home screen's one read. It sits where src/lib/auth.ts, src/lib/bookings.ts
// and src/lib/enquiries.ts sit: below the store, above src/lib/api.ts. Screens
// never import it; they go through src/stores/home.ts, and
// src/lib/boundary.test.ts fails if one tries.
//
// **One request for three blocks**, which is a decision on the server and not a
// convenience here: the owed headline is the sum of the attention block's
// client_balance rows, so splitting it would mean either the server computing
// every booking's waiting-on state twice or this client defining a money
// figure. It also makes Home one thing to cache, which is what business logic
// 23.2 asks of the screen that most has to work with no signal.
//
// The payload wraps its three blocks in `data` and its flags in `meta`, as
// every other endpoint does, and both are unwrapped here so that nothing above
// this line knows the envelope exists.

export async function home(): Promise<Home> {
  const response = await api.get<{ data: HomeSummary, meta: HomeMeta }>('/api/home')

  return { summary: response.data, meta: response.meta }
}
