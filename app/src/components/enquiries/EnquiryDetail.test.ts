import { describe, expect, it } from 'vitest'
import { defineComponent, h, ref } from 'vue'
import { settle } from '@/lib/testHelpers'
import { mountWithCleanup } from '@/lib/testMount'
import EnquiryDetail from '@/components/enquiries/EnquiryDetail.vue'
import type { FeatureMap } from '@/types/auth'
import type { Enquiry, EnquiryDetail as Detail } from '@/types/enquiries'

// The detail, which is the booking screen against a record where most of it is
// still empty. Which sections it draws is tested in enquirySections.test.ts;
// this is about what reaches the screen.

const mount = mountWithCleanup()

const today = new Date(2026, 8, 6)

const allOn: FeatureMap = {
  enquiries: true,
  intake_forms: true,
  agreements: true,
  invoicing: true,
  payment_tracking: true,
  automation: true,
  travel_estimates: true,
  photos: true,
  feedback_requests: true,
}

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

// The section headings, read as headings rather than as body text: the
// closing sentence names Payments and the agreement on purpose, so a substring
// search over the whole screen would find them whether or not a section was
// drawn.
function headings(): string[] {
  return [...document.querySelectorAll('h3')].map((heading) => heading.textContent?.trim() ?? '')
}

// One host component for the whole file, for the reason AnchoredSheet's tests
// give: several in one file is a file where two of them quietly differ.
const given = ref<{ enquiry: Enquiry, detail: Detail | null, features: FeatureMap | null }>({
  enquiry: enquiry(),
  detail: null,
  features: allOn,
})

const Host = defineComponent({
  setup: () => () => h(EnquiryDetail, {
    enquiry: given.value.enquiry,
    detail: given.value.detail,
    features: given.value.features,
    today,
  }),
})

async function show(
  record: Enquiry,
  detail: Detail | null = null,
  features: FeatureMap | null = allOn,
): Promise<void> {
  given.value = { enquiry: record, detail, features }

  await mount(Host, '/enquiries/1')
  await settle()
}

/**
 * The header draws from the list row, which carries every field in it, so
 * tapping a row shows the name, the date and the stage at once and fills the
 * rest in when the second request arrives.
 */
describe('the header', () => {
  it('draws from the list row alone, with no detail yet', async () => {
    await show(enquiry())

    const text = document.body.textContent ?? ''

    expect(text).toContain('Imogen Hartwell')
    expect(text).toContain('Marlbrook Hall')
    expect(text).toContain('Possible')
    // And no empty state in the meantime.
    expect(text).not.toContain('could not be found')
  })

  /**
   * Null and zero are different facts. "No price yet" is an enquiry nobody has
   * quoted; "£0" is a job somebody is doing for nothing. This screen is the
   * first to render the distinction the API made total_minor nullable for.
   */
  it('says No price yet when nobody has priced it', async () => {
    await show(enquiry({ total_minor: null }))

    expect(document.body.textContent).toContain('No price yet')
  })

  it('says a real zero for a job priced at nothing', async () => {
    await show(enquiry({ total_minor: 0 }))

    expect(document.body.textContent).toContain('£0')
    expect(document.body.textContent).not.toContain('No price yet')
  })
})

/**
 * Business logic 5.5.1: the source is kept on the record, so that when an
 * extraction is wrong the artist can see what it was working from. It is the
 * difference between a name from four months ago and a conversation that can
 * be picked up, and it is the reason the detail is a second request.
 */
describe('what they said', () => {
  it('appears once the detail arrives', async () => {
    await show(enquiry(), {
      ...enquiry(),
      enquiry_message: 'We are at Marlbrook Hall on the 29th and there would be four of us.',
      party_size: 4,
      notes: [],
    })

    expect(document.body.textContent).toContain('What they said')
    expect(document.body.textContent).toContain('there would be four of us')
  })

  // Paired, so it cannot pass by being on every record: most enquiries are
  // typed in during a phone call and carry no message at all.
  it('is absent when the enquiry arrived without one', async () => {
    await show(enquiry(), { ...enquiry(), enquiry_message: null, party_size: null, notes: [] })

    expect(document.body.textContent).not.toContain('What they said')
  })
})

describe('the sections', () => {
  it('draws the ones the stage has earned and no others', async () => {
    await show(enquiry({ stage: 'new' }))

    // Price arrives at Possible, and the other five when it becomes a booking.
    expect(headings()).toEqual(['Dates and venue', 'Party', 'Notes', 'Activity'])
  })

  it('adds the price at Possible', async () => {
    await show(enquiry({ stage: 'possible' }))

    expect(headings()).toContain('Price')
  })

  /**
   * **The feature beats the stage.** Business logic 21 and 6: the app only
   * ever asks about things the artist has switched on, so a section for a
   * switched-off feature is never drawn at any stage.
   */
  it('draws nothing for a switched-off feature, at the stage that would earn it', async () => {
    await show(enquiry({ stage: 'provisional' }), null, { ...allOn, invoicing: false })

    expect(headings()).not.toContain('Payments')
    // Paired, so it cannot pass on a screen that drew no sections at all.
    expect(headings()).toContain('Agreement')
  })

  // The teaching the nine headings were being asked to do costs one sentence
  // instead, at the foot rather than as empty blocks above the fold.
  it('says in one sentence what is still to come', async () => {
    await show(enquiry())

    expect(document.body.textContent).toContain('Price appears once this moves to Possible')
  })
})
