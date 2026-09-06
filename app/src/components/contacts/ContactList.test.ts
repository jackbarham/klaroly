import { describe, expect, it } from 'vitest'
import { element } from '@/lib/testHelpers'
import { mountWithCleanup } from '@/lib/testMount'
import ContactList from '@/components/contacts/ContactList.vue'
import { groupContacts, matches } from '@/lib/contactList'
import type { Contact } from '@/types/contacts'

// What the list draws once the rules in src/lib/contactList.ts have run. The
// rules themselves are tested there, without mounting anything; this is about
// the two empty states being different states, and about which of a row's two
// lines is the strong one.
//
// The contacts are built here rather than taken from src/lib/contactFixtures.
// That file is a stand-in for an endpoint and no component may import it, but
// the better reason is that a test asserting a query matches nobody should say
// which two people it is filtering rather than inherit twenty-two.

const mount = mountWithCleanup()

const today = new Date(2026, 8, 6)

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
    booking_count: 1,
    next_booking: {
      id: 101,
      event_type: 'main',
      date: '2026-09-12',
      venue_name: 'Ashgrove Manor',
      city: 'Hertford',
      stage: 'confirmed',
      total_minor: 96000,
      currency: 'GBP',
    },
    last_booking: null,
    outstanding: [],
    ...over,
  }
}

const people = [contact(), contact({ id: 2, first_name: 'Nadia', last_name: 'Okonkwo' })]

async function list(query: string, over: Record<string, unknown> = {}) {
  const visible = people.filter((entry) => matches(entry, query))

  return mount(ContactList, '/contacts', {
    groups: groupContacts(visible, 'recent', today),
    leadWith: 'name',
    showInitials: true,
    showAmounts: true,
    today,
    listId: 'contacts',
    activeId: null,
    selectedId: null,
    filtering: query.trim() !== '',
    ...over,
  })
}

describe('the contacts list', () => {
  it('draws a row for each contact under a group heading', async () => {
    const { host } = await list('')

    expect(host.querySelectorAll('[role="option"]')).toHaveLength(2)
    expect(element(host, 'h2').textContent?.trim()).toBe('Upcoming')
  })

  // A query matching nothing is an empty state, not an error and not a blank
  // column.
  it('shows the no-matches empty state when the filter matches nobody', async () => {
    const { host } = await list('zzzz')

    expect(host.querySelectorAll('[role="option"]')).toHaveLength(0)
    expect(host.textContent).toContain('Nobody matches that')
  })

  // The two empty states are different states, and this is the pair that makes
  // the assertion above mean something: with no contacts at all and nothing
  // typed, the wording has to be the other one. Without this, "Nobody matches
  // that" would pass just as happily for an account with no contacts, which is
  // a new artist being told her filter is too narrow.
  it('shows the nobody-yet empty state when there is no filter and no contacts', async () => {
    const { host } = await mount(ContactList, '/contacts', {
      groups: [],
      leadWith: 'name',
      showInitials: true,
      showAmounts: true,
      today,
      listId: 'contacts',
      activeId: null,
      selectedId: null,
      filtering: false,
    })

    expect(host.textContent).toContain('No contacts yet')
    expect(host.textContent).not.toContain('Nobody matches that')
  })

  it('leads with the name, and drops to the booking underneath', async () => {
    const { host } = await list('')
    const row = element(host, '[role="option"]')

    expect(element(row, '.contact-row__lead').textContent?.trim()).toBe('Imogen Hartwell')
    expect(row.textContent).toContain('12 Sep · Ashgrove Manor')
  })

  // The one thing the "Rows lead with" setting moves. Nothing else about the
  // row changes, which is why this asserts the same two strings are still both
  // there and only their order has swapped.
  it('leads with the booking when the setting says so', async () => {
    const { host } = await list('', { leadWith: 'booking' })
    const row = element(host, '[role="option"]')

    expect(element(row, '.contact-row__lead').textContent?.trim()).toBe('12 Sep · Ashgrove Manor')
    expect(row.textContent).toContain('Imogen Hartwell')
  })

  it('drops the initials mark when the setting says so', async () => {
    const withMark = await list('')

    expect(element(withMark.host, '[role="option"]').textContent).toContain('IH')

    const without = await list('', { showInitials: false })

    expect(element(without.host, '[role="option"]').textContent).not.toContain('IH')
  })

  // A listbox, because the filter field arrows through it while keeping focus,
  // and each band is a group so that its sticky heading is bounded by its own
  // band rather than pinning to the top of the page with every other heading.
  it('is a listbox of groups, so the sticky headings cannot stack', async () => {
    const { host } = await list('')

    expect(element(host, '[role="listbox"]')).toBeTruthy()
    expect(host.querySelectorAll('[role="group"]').length).toBeGreaterThan(0)
  })
})
