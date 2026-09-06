import { api } from '@/lib/api'
import type { Enquiry, EnquiryDetail, EnquiryMeta, LostReason } from '@/types/enquiries'
import type { BookingStage } from '@/types/bookings'

// The enquiries screen's two reads and its one write. It sits where
// src/lib/auth.ts and src/lib/bookings.ts sit: below the store, above
// src/lib/api.ts. Screens never import it; they go through
// src/stores/enquiries.ts, and src/lib/boundary.test.ts fails if one tries.
//
// All three wrap their payload in `data`, as every other endpoint does, and
// all three unwrap it here so that nothing above this line knows the envelope
// exists.

export interface EnquiryList {
  enquiries: Enquiry[]
  meta: EnquiryMeta
}

/**
 * Every enquiry the account holds, ordered by neglect.
 *
 * No parameters, and there will not be any. The screen holds the whole list in
 * memory and does its own sorting, grouping and filtering with no round trip,
 * which is what makes the filter box instant and what makes the screen work
 * with no signal. A page size or a search parameter would buy nothing and take
 * that away.
 */
export async function enquiries(): Promise<EnquiryList> {
  const response = await api.get<{ data: Enquiry[], meta: EnquiryMeta }>('/api/enquiries')

  return { enquiries: response.data, meta: response.meta }
}

// One enquiry opened, which is the list row plus the message, the party size
// and the notes. A second request rather than three more fields on the list,
// because a pasted message across five hundred rows is not a list payload.
export async function enquiry(id: number): Promise<EnquiryDetail> {
  const response = await api.get<{ data: EnquiryDetail }>(`/api/enquiries/${id}`)

  return response.data
}

/**
 * Move an enquiry to another stage, and say why when that stage is lost.
 *
 * The one way a record crosses the line between the two lists: nothing in the
 * system promotes an enquiry on its own. It answers with the detail shape, so
 * the screen replaces what it is holding rather than following the write with
 * a read, and a record that has become provisional comes back anyway for the
 * list to remove.
 */
export async function setStage(
  id: number,
  stage: BookingStage,
  lostReason: LostReason | null = null,
): Promise<EnquiryDetail> {
  const response = await api.patch<{ data: EnquiryDetail }>(`/api/enquiries/${id}`, {
    stage,
    // Sent as null rather than left out when there is none. The API's rule is
    // prohibited_unless rather than missing_unless for exactly this: a client
    // that always sends both fields and puts null in the second is saying
    // something true.
    lost_reason: lostReason,
  })

  return response.data
}
