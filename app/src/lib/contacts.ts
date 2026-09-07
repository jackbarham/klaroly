import { api } from '@/lib/api'
import type { Contact, ContactMeta } from '@/types/contacts'

// The contacts screen's one read. It sits where src/lib/enquiries.ts and
// src/lib/bookings.ts sit: below the store, above src/lib/api.ts. Screens
// never import it; they go through src/stores/contacts.ts, and both
// src/lib/boundary.test.ts and src/lib/contacts.guards.test.ts fail if one
// tries.
//
// It replaces src/lib/contactFixtures.ts, which stood in for this endpoint
// while the screen was built. Nothing above this line changed shape when it
// went: the store already awaited a promise, and the field names it awaited
// are now the resource's.
//
// The payload is wrapped in `data` with a `meta` beside it, as the enquiries
// list is, and both are unwrapped here so nothing above this line knows the
// envelope exists.

export interface ContactList {
  contacts: Contact[]
  meta: ContactMeta
}

/**
 * Every contact the account holds, ordered by what is coming up.
 *
 * No parameters, and there will not be any. The screen holds the whole list in
 * memory and does its own sorting, grouping and filtering with no round trip,
 * which is what makes the filter box instant and what makes the screen work
 * with no signal. A page size or a search parameter would buy nothing and take
 * that away.
 *
 * The screen re-sorts anyway, so the server's order reaches it only as the
 * tie-break between two contacts the screen cannot separate. What the order is
 * really for is the ceiling: because a contact with a future date sorts above
 * every contact without one, a truncated response is the useful end of the
 * list rather than an arbitrary slice of it.
 */
export async function contacts(): Promise<ContactList> {
  const response = await api.get<{ data: Contact[], meta: ContactMeta }>('/api/contacts')

  return { contacts: response.data, meta: response.meta }
}
