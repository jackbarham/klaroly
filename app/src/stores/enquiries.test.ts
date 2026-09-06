import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { jsonResponse } from '@/lib/testHelpers'
import { useEnquiriesStore } from '@/stores/enquiries'
import type { Enquiry, EnquiryDetail } from '@/types/enquiries'

// What the store does that no component may: the two requests, and what a
// stage change leaves behind.

function enquiry(over: Partial<Enquiry> = {}): Enquiry {
  return {
    id: 1,
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
    ...over,
  }
}

function detail(over: Partial<EnquiryDetail> = {}): EnquiryDetail {
  return { ...enquiry(), enquiry_message: null, party_size: null, notes: [], ...over }
}

const fetchMock = vi.fn()

beforeEach(() => {
  setActivePinia(createPinia())
  fetchMock.mockReset()
  vi.stubGlobal('fetch', fetchMock)
  window.localStorage.clear()

  // api.ts fetches the CSRF cookie before a non-GET when this is absent, which
  // would put an extra call in front of every write here. The web app has one
  // by the time any of this runs; see src/lib/auth.test.ts, which does the
  // same.
  document.cookie = 'XSRF-TOKEN=token'
})

afterEach(() => {
  vi.unstubAllGlobals()
})

function listResponse(rows: Enquiry[]): Response {
  return jsonResponse(200, { data: rows, meta: { total: rows.length, returned: rows.length, truncated: false } })
}

describe('the list', () => {
  it('fetches once however many times it is asked', async () => {
    fetchMock.mockResolvedValue(listResponse([enquiry()]))

    const store = useEnquiriesStore()

    await Promise.all([store.load(), store.load()])
    await store.load()

    expect(fetchMock).toHaveBeenCalledTimes(1)
    expect(store.status).toBe('ready')
    expect(store.meta?.total).toBe(1)
  })

  it('says so rather than throwing when the request fails', async () => {
    fetchMock.mockResolvedValue(jsonResponse(500))

    const store = useEnquiriesStore()

    await store.load()

    expect(store.status).toBe('failed')
    expect(store.enquiries).toEqual([])
  })
})

/**
 * The header draws from the list row while the detail is in flight, which is
 * what the store has to make possible: find() answers immediately and the
 * detail arrives afterwards.
 */
describe('opening one', () => {
  it('has the list row before the detail request resolves', async () => {
    fetchMock.mockResolvedValueOnce(listResponse([enquiry({ id: 7, client_name: 'Freya Loxley' })]))

    const store = useEnquiriesStore()

    await store.load()

    let resolve: (value: Response) => void = () => {}

    fetchMock.mockReturnValueOnce(new Promise<Response>((done) => { resolve = done }))

    const opening = store.openDetail(7)

    // Mid-flight: the row is there, the detail is not, and nothing says the
    // record could not be found.
    expect(store.detailStatus).toBe('loading')
    expect(store.detail).toBeNull()
    expect(store.find(7)?.client_name).toBe('Freya Loxley')

    resolve(jsonResponse(200, { data: detail({ id: 7, enquiry_message: 'Hello' }) }))
    await opening

    expect(store.detailStatus).toBe('ready')
    expect(store.detail?.enquiry_message).toBe('Hello')
  })
})

/**
 * One write, and the row is replaced from what comes back rather than patched
 * here or refetched, so the list and the open detail cannot end up disagreeing
 * about the stage they just changed.
 */
describe('changing the stage', () => {
  it('calls the write once and replaces exactly one row', async () => {
    fetchMock.mockResolvedValueOnce(listResponse([
      enquiry({ id: 1 }),
      enquiry({ id: 2, client_name: 'Ines Marchetti' }),
    ]))

    const store = useEnquiriesStore()

    await store.load()

    fetchMock.mockResolvedValueOnce(jsonResponse(200, { data: detail({ id: 1, stage: 'quoted' }) }))

    await store.setStage(1, 'quoted')

    // The list call and the write, and nothing else: no refetch afterwards.
    expect(fetchMock).toHaveBeenCalledTimes(2)
    expect(store.enquiries).toHaveLength(2)
    expect(store.find(1)?.stage).toBe('quoted')
    expect(store.find(2)?.client_name).toBe('Ines Marchetti')
  })

  it('sends the reason when the stage is lost', async () => {
    fetchMock.mockResolvedValueOnce(listResponse([enquiry({ id: 1 })]))

    const store = useEnquiriesStore()

    await store.load()

    fetchMock.mockResolvedValueOnce(jsonResponse(200, {
      data: detail({ id: 1, stage: 'lost', lost_reason: 'already_booked', lost_side: 'artist' }),
    }))

    await store.setStage(1, 'lost', 'already_booked')

    const body = JSON.parse(String(fetchMock.mock.calls[1][1].body))

    expect(body).toEqual({ stage: 'lost', lost_reason: 'already_booked' })
    // Lost is archived rather than gone: it stays in the list for the setting
    // that shows it.
    expect(store.find(1)?.stage).toBe('lost')
  })

  /**
   * A record that has become provisional is no longer an enquiry and leaves
   * the list. It still comes back from the API, which is what lets the screen
   * say what happened to it rather than watching a row vanish.
   */
  it('removes a row that has become a booking, and still returns it', async () => {
    fetchMock.mockResolvedValueOnce(listResponse([enquiry({ id: 1 }), enquiry({ id: 2 })]))

    const store = useEnquiriesStore()

    await store.load()

    fetchMock.mockResolvedValueOnce(jsonResponse(200, { data: detail({ id: 1, stage: 'provisional' }) }))

    const written = await store.setStage(1, 'provisional')

    expect(written.stage).toBe('provisional')
    expect(store.find(1)).toBeNull()
    expect(store.enquiries).toHaveLength(1)
  })

  it('leaves the list alone when the write fails', async () => {
    fetchMock.mockResolvedValueOnce(listResponse([enquiry({ id: 1 })]))

    const store = useEnquiriesStore()

    await store.load()

    fetchMock.mockResolvedValueOnce(jsonResponse(422, { message: 'no' }))

    await expect(store.setStage(1, 'quoted')).rejects.toThrow()
    expect(store.find(1)?.stage).toBe('possible')
  })
})
