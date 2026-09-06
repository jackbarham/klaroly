import { describe, expect, it } from 'vitest'
import { defineComponent, h, ref } from 'vue'
import { settle } from '@/lib/testHelpers'
import { mountWithCleanup } from '@/lib/testMount'
import EnquiryList from '@/components/enquiries/EnquiryList.vue'
import { groupEnquiries, type EnquiryGroup } from '@/lib/enquiryList'
import { defaultSettings } from '@/lib/enquiryView'
import type { Enquiry } from '@/types/enquiries'

// What the list draws once the rules in src/lib/enquiryList.ts have run, and
// the two things about it that are not rules: the roles, and the keyboard.

const mount = mountWithCleanup()

const today = new Date(2026, 8, 6)

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
    event: {
      type: 'main',
      date: '2027-05-29',
      location_type: 'venue',
      venue_name: 'Marlbrook Hall',
      city: 'Ludworth',
    },
    has_trial: false,
    lost_reason: null,
    lost_side: null,
    clash: null,
    ...over,
  }
}

const staged = ref<Enquiry[]>([])

// showLost defaults to false, as the setting does, so a test that wants to see
// a lost row has to ask for it. That is the rule working rather than a
// nuisance: the archive is hidden until the artist says otherwise.
// One host component for the whole file rather than one per test: a test file
// that defines several is a file where two of them quietly differ.
const given = ref<{ groups: EnquiryGroup[], selectedId: number | null, filtering: boolean }>({
  groups: [],
  selectedId: null,
  filtering: false,
})

const Host = defineComponent({
  setup: () => () => h(EnquiryList, {
    groups: given.value.groups,
    settings: defaultSettings,
    today,
    listId: 'list',
    selectedId: given.value.selectedId,
    filtering: given.value.filtering,
    onStage: (record: Enquiry) => { staged.value.push(record) },
  }),
})

async function show(
  enquiries: Enquiry[],
  selectedId: number | null = null,
  showLost = false,
): Promise<void> {
  staged.value = []
  given.value = {
    groups: groupEnquiries(enquiries, 'staleness', today, showLost),
    selectedId,
    filtering: false,
  }

  await mount(Host, '/enquiries')
  await settle()
}

/**
 * The one thing on this screen not to copy from contacts.
 *
 * The contacts list is a listbox because its rows hold nothing interactive.
 * This one puts the stage pill inside the row, and an ARIA option may not
 * contain a control: the button would be flattened out of the accessibility
 * tree or the row would stop being an option, and aria-activedescendant could
 * never reach the pill anyway, because it moves a virtual cursor and a control
 * needs real focus.
 */
describe('the roles', () => {
  it('is a list and not a listbox', async () => {
    await show([enquiry()])

    expect(document.querySelector('[role="listbox"]')).toBeNull()
    expect(document.querySelector('[role="option"]')).toBeNull()
    expect(document.querySelector('[aria-activedescendant]')).toBeNull()
    // Paired, so the assertions above cannot pass on a list that rendered
    // nothing at all.
    expect(document.querySelectorAll('[role="list"]').length).toBeGreaterThan(0)
    expect(document.querySelectorAll('[role="listitem"], li').length).toBe(1)
  })

  it('draws a real link to the enquiry', async () => {
    await show([enquiry({ id: 42 })])

    expect(document.querySelector('a')?.getAttribute('href')).toBe('/enquiries/42')
  })
})

describe('the stage pill', () => {
  it('asks for the sheet rather than navigating', async () => {
    await show([enquiry({ id: 7 })])

    const before = window.location.pathname
    const pill = document.querySelector<HTMLButtonElement>('button[aria-haspopup="dialog"]')

    pill?.click()
    await settle()

    expect(staged.value.map((record) => record.id)).toEqual([7])
    // The click never reaches the row's link, so nothing moved.
    expect(window.location.pathname).toBe(before)
  })

  // A lost enquiry has ended, so its pill says which of the two endings it was
  // and is not a control.
  it('is not a control on a lost enquiry', async () => {
    await show([enquiry({ stage: 'lost', lost_side: 'artist', lost_reason: 'already_booked' })], null, true)

    expect(document.querySelector('button[aria-haspopup="dialog"]')).toBeNull()
    expect(document.body.textContent).toContain('Turned down')
  })
})

/**
 * A roving tabindex, because a control inside a row can only be reached by
 * real focus. Exactly one row is reachable by Tab, so Tab lands in the list
 * once rather than walking forty rows before reaching anything after it.
 */
describe('the keyboard', () => {
  it('parks Tab on one row and moves focus between them with the arrows', async () => {
    await show([
      enquiry({ id: 1, last_touched_at: new Date(2026, 7, 1, 12).toISOString() }),
      enquiry({ id: 2, last_touched_at: new Date(2026, 8, 1, 12).toISOString() }),
    ])

    const links = [...document.querySelectorAll<HTMLAnchorElement>('a')]

    expect(links.map((link) => link.tabIndex)).toEqual([0, -1])

    const list = document.getElementById('list')

    // From outside the rows, either arrow lands on the first: pressing up
    // before down is asking to get into the list, not to wrap to the bottom.
    list?.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowUp', bubbles: true }))
    await settle()

    expect(document.activeElement).toBe(links[0])

    list?.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }))
    await settle()

    expect(document.activeElement).toBe(links[1])

    // And it stops at the end rather than wrapping.
    list?.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }))
    await settle()

    expect(document.activeElement).toBe(links[1])
  })

  // Tabbing back into the list after looking at a card returns to that card's
  // row rather than to the top of a list of forty.
  it('parks Tab on the open row when there is one', async () => {
    await show([
      enquiry({ id: 1, last_touched_at: new Date(2026, 7, 1, 12).toISOString() }),
      enquiry({ id: 2, last_touched_at: new Date(2026, 8, 1, 12).toISOString() }),
    ], 2)

    expect([...document.querySelectorAll<HTMLAnchorElement>('a')].map((link) => link.tabIndex)).toEqual([-1, 0])
  })
})

/**
 * Two empty states, and which one it is matters. Nobody at all is a new
 * account; nobody matching is a query that is too narrow.
 */
describe('the empty states', () => {
  it('says the list is empty when it is', async () => {
    await show([])

    expect(document.body.textContent).toContain('No enquiries yet')
  })

  it('says nobody matches when the filter is what emptied it', async () => {
    staged.value = []
    given.value = { groups: [], selectedId: null, filtering: true }

    await mount(Host, '/enquiries')
    await settle()

    expect(document.body.textContent).toContain('Nobody matches that')
    expect(document.body.textContent).not.toContain('No enquiries yet')
  })
})

/**
 * Null and zero are different facts, which is what the API made total_minor
 * nullable for and what this screen is the first to render.
 */
describe('the total', () => {
  it('shows nothing at all when nobody has priced it', async () => {
    await show([enquiry({ total_minor: null })])

    expect(document.body.textContent).not.toContain('£')
  })

  it('shows a real zero for a job priced at nothing', async () => {
    await show([enquiry({ total_minor: 0 })])

    expect(document.body.textContent).toContain('£0')
  })

  it('shows the figure when there is one', async () => {
    await show([enquiry({ total_minor: 86000 })])

    expect(document.body.textContent).toContain('£860')
  })
})

// The absent date is a first-class value: a row that says nothing where a date
// goes reads as a bug in the app rather than a fact about the wedding.
it('says No date yet on a row with no date', async () => {
  await show([enquiry({ event: null })])

  expect(document.body.textContent).toContain('No date yet')
})
