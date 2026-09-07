import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { jsonResponse, stubFetch } from '@/lib/testHelpers'
import { useContactsStore } from '@/stores/contacts'
import type { Contact } from '@/types/contacts'

// What the store does that no component may: the one request, the ceiling it
// carries back, and the deletion that is still local.
//
// It is new with the swap onto GET /api/contacts. While the screen was on
// src/lib/contactFixtures.ts there was nothing here to test: load() awaited a
// resolved promise that could not fail.

function contact(over: Partial<Contact> = {}): Contact {
  return {
    id: 1,
    first_name: 'Imogen',
    last_name: 'Hartwell',
    email: 'imogen.hartwell@example.com',
    phone: '07700 900461',
    address_line_1: null,
    address_line_2: null,
    city: 'Hertford',
    postcode: null,
    country: 'GB',
    bookings: [],
    booking_count: 0,
    next_booking: null,
    last_booking: null,
    outstanding: [],
    ...over,
  }
}

const fetchMock = stubFetch()

beforeEach(() => {
  setActivePinia(createPinia())
  window.localStorage.clear()
})

function listResponse(rows: Contact[], truncated = false): Response {
  return jsonResponse(200, {
    data: rows,
    meta: { total: rows.length, returned: rows.length, truncated },
  })
}

describe('the list', () => {
  it('fetches once however many times it is asked', async () => {
    fetchMock.mockResolvedValue(listResponse([contact()]))

    const store = useContactsStore()

    await Promise.all([store.load(), store.load()])
    await store.load()

    expect(fetchMock).toHaveBeenCalledTimes(1)
    expect(store.status).toBe('ready')
    expect(store.contacts).toHaveLength(1)
  })

  it('asks the contacts endpoint and sends no parameters', async () => {
    fetchMock.mockResolvedValue(listResponse([contact()]))

    await useContactsStore().load()

    expect(String(fetchMock.mock.calls[0][0])).toContain('/api/contacts')
    expect(String(fetchMock.mock.calls[0][0])).not.toContain('?')
  })

  it('says so rather than throwing when the request fails', async () => {
    fetchMock.mockResolvedValue(jsonResponse(500))

    const store = useContactsStore()

    await store.load()

    expect(store.status).toBe('failed')
    expect(store.contacts).toEqual([])
  })

  // The ceiling is a flag in meta rather than a 422, so the store has to carry
  // it back: an account over the cap is looking at a partial list and nothing
  // else in the app can tell.
  it('keeps the ceiling the endpoint reports', async () => {
    fetchMock.mockResolvedValue(jsonResponse(200, {
      data: [contact()],
      meta: { total: 1200, returned: 1, truncated: true },
    }))

    const store = useContactsStore()

    await store.load()

    expect(store.meta?.total).toBe(1200)
    expect(store.meta?.truncated).toBe(true)
  })

  // Paired with the assertion above, so "it carries the flag" is not passing
  // against a store that reports every list as truncated.
  it('reports a list under the ceiling as complete', async () => {
    fetchMock.mockResolvedValue(listResponse([contact()]))

    const store = useContactsStore()

    await store.load()

    expect(store.meta?.truncated).toBe(false)
  })
})

// Local only, until there is a delete route. It is in the store so the list
// and the detail cannot disagree about who exists, and the day it becomes a
// request the change is one function.
describe('removing a contact', () => {
  it('drops the row without asking the API anything', async () => {
    fetchMock.mockResolvedValue(listResponse([contact({ id: 1 }), contact({ id: 2 })]))

    const store = useContactsStore()

    await store.load()
    store.remove(1)

    expect(store.contacts.map((entry) => entry.id)).toEqual([2])
    expect(store.find(1)).toBeNull()
    expect(fetchMock).toHaveBeenCalledTimes(1)
  })
})
