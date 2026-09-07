import { describe, expect, it } from 'vitest'
import EnquiryDetailView from '@/views/enquiries/EnquiryDetailView.vue'
import { jsonResponse, sampleToday, settle, stubFetch } from '@/lib/testHelpers'
import { mountWithCleanup } from '@/lib/testMount'
import { sampleEnquiry } from '@/lib/enquiries.sample'
import type { EnquiryDetail } from '@/types/enquiries'

// The view around the detail: what it draws when the record is there, and
// what it says when it is not. The detail itself is EnquiryDetail.test.ts.

const mount = mountWithCleanup()

const today = sampleToday

const fetchMock = stubFetch()

function detail(): EnquiryDetail {
  return { ...sampleEnquiry({ id: 7, event: null }), enquiry_message: null, party_size: null, notes: [] }
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
