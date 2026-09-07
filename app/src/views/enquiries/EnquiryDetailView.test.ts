import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import EnquiryDetailView from '@/views/enquiries/EnquiryDetailView.vue'
import { jsonResponse, settle } from '@/lib/testHelpers'
import { mountWithCleanup } from '@/lib/testMount'
import type { EnquiryDetail } from '@/types/enquiries'

// The view around the detail: what it draws when the record is there, and
// what it says when it is not. The detail itself is EnquiryDetail.test.ts.

const mount = mountWithCleanup()

const today = new Date(2026, 8, 6)

const fetchMock = vi.fn<typeof fetch>()

beforeEach(() => {
  fetchMock.mockReset()
  vi.stubGlobal('fetch', fetchMock)
})

afterEach(() => {
  vi.unstubAllGlobals()
})

function detail(): EnquiryDetail {
  return {
    id: 7,
    stage: 'possible',
    client_name: 'Imogen Hartwell',
    contact_id: 10,
    source: 'web_form',
    source_booking: null,
    last_touched_at: new Date(2026, 8, 3, 12).toISOString(),
    waiting_on: null,
    total_minor: null,
    currency: 'GBP',
    event: null,
    has_trial: false,
    lost_reason: null,
    lost_side: null,
    clash: null,
    enquiry_message: null,
    party_size: null,
    notes: [],
  }
}

describe('the enquiry detail view', () => {
  // A deep link into a record the list has not loaded falls back to the
  // detail response itself, which is the same shape plus three fields.
  it('draws the record from the detail response on a deep link', async () => {
    fetchMock.mockResolvedValue(jsonResponse(200, { data: detail() }))

    const { host } = await mount(EnquiryDetailView, '/enquiries/7', { today })

    await settle()

    expect(host.textContent).toContain('Imogen Hartwell')
    expect(host.textContent).not.toContain('could not be found')
  })

  // Paired with the absence above, so that one cannot pass on a view that
  // never says it: a record that is not there is said in words, and this is
  // the one place in the app those words are rendered.
  it('says the record could not be found when the request answers so', async () => {
    fetchMock.mockResolvedValue(jsonResponse(404, { message: 'Not Found' }))

    const { host } = await mount(EnquiryDetailView, '/enquiries/7', { today })

    await settle()

    expect(host.textContent).toContain('That enquiry could not be found')
    expect(host.textContent).not.toContain('Imogen Hartwell')
  })
})
