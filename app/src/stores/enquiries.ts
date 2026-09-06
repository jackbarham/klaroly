import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { enquiries as fetchEnquiries, enquiry as fetchEnquiry, setStage as writeStage } from '@/lib/enquiries'
import { defaultSettings, readSettings, writeSettings, type EnquiryViewSettings } from '@/lib/enquiryView'
import type { BookingStage } from '@/types/bookings'
import type { Enquiry, EnquiryDetail, EnquiryMeta, LostReason } from '@/types/enquiries'

// The enquiries the screen draws, the one record currently open, and how this
// device likes to read them. Components read this store and never call the
// data layer themselves.
//
// **Two requests, and they are two for different reasons.** The list is one
// payload, held whole in memory, with every sort, group and filter done in the
// browser: perhaps forty live enquiries and a few hundred archived ones, so
// there is no pagination, no infinite scroll, no virtualisation, no debounce
// on the filter box and no spinner, because there is nothing to wait for. That
// is what makes this screen work in a hotel basement with no signal. The
// detail is a second request because it carries the original message, which is
// a pasted WhatsApp thread: five hundred of those is not a list payload, and a
// detail opened by tapping does not have to work offline.

export type LoadStatus = 'idle' | 'loading' | 'ready' | 'failed'

// The stages this list holds. Anything from provisional onwards belongs to the
// bookings list, so a record that reaches one leaves this one.
const listed: BookingStage[] = ['new', 'in_conversation', 'possible', 'quoted', 'lost']

export const useEnquiriesStore = defineStore('enquiries', () => {
  const enquiries = ref<Enquiry[]>([])
  const status = ref<LoadStatus>('idle')
  const meta = ref<EnquiryMeta | null>(null)

  const detail = ref<EnquiryDetail | null>(null)
  const detailStatus = ref<LoadStatus>('idle')

  // Read once, when the store is created, so the first render is already in
  // the shape this person left it in and no setting is seen to change after
  // the list has drawn. readSettings copes with a storage that throws.
  const settings = ref<EnquiryViewSettings>(readSettings())

  /**
   * Fetches once. Coming back to Enquiries from another tab does not refetch,
   * and two components mounting together do not fetch twice.
   */
  async function load(): Promise<void> {
    if (status.value === 'loading' || status.value === 'ready') {
      return
    }

    status.value = 'loading'

    try {
      const answer = await fetchEnquiries()

      enquiries.value = answer.enquiries
      meta.value = answer.meta
      status.value = 'ready'
    } catch {
      status.value = 'failed'
    }
  }

  async function retry(): Promise<void> {
    status.value = 'idle'

    await load()
  }

  const byId = computed(() => new Map(enquiries.value.map((enquiry) => [enquiry.id, enquiry])))

  function find(id: number): Enquiry | null {
    return byId.value.get(id) ?? null
  }

  /**
   * Open one enquiry.
   *
   * The old detail is cleared immediately, so a slow request cannot leave the
   * previous person's message under the new person's name. What fills the gap
   * is not an empty state: the header's fields are all on the list row
   * already, so the screen draws the name, the date and the stage from find()
   * while this is in flight. Nothing makes the artist wait to see what she
   * just tapped.
   */
  async function openDetail(id: number): Promise<void> {
    detail.value = null
    detailStatus.value = 'loading'

    try {
      const found = await fetchEnquiry(id)

      // A second tap while the first was in flight wins: without this, the
      // slower of two requests would overwrite the faster one and the screen
      // would settle on whichever server answered last rather than on whatever
      // was asked for last.
      if (detailStatus.value !== 'loading') {
        return
      }

      detail.value = found
      detailStatus.value = 'ready'
    } catch {
      detailStatus.value = 'failed'
    }
  }

  function closeDetail(): void {
    detail.value = null
    detailStatus.value = 'idle'
  }

  /**
   * Move an enquiry to another stage, and say why when that stage is lost.
   *
   * One write, and the row is replaced from what comes back rather than
   * patched here or refetched: the response is the whole record, so the list
   * and the open detail cannot end up disagreeing about the stage they just
   * changed.
   *
   * A record that has become provisional is no longer an enquiry and leaves
   * the list. It still comes back from the API, which is what lets the screen
   * say what happened to it rather than watching a row vanish.
   */
  async function setStage(
    id: number,
    stage: BookingStage,
    lostReason: LostReason | null = null,
  ): Promise<EnquiryDetail> {
    const written = await writeStage(id, stage, lostReason)

    if (listed.includes(written.stage)) {
      enquiries.value = enquiries.value.map((enquiry) => (enquiry.id === id ? written : enquiry))
    } else {
      enquiries.value = enquiries.value.filter((enquiry) => enquiry.id !== id)
    }

    if (detail.value?.id === id) {
      detail.value = written
    }

    return written
  }

  // A patch rather than a whole object, because the menu changes one setting
  // at a time and stays open while it does, so the list redraws underneath and
  // the setting is judged by its effect rather than by its name.
  function update(patch: Partial<EnquiryViewSettings>): void {
    settings.value = { ...settings.value, ...patch }

    writeSettings(settings.value)
  }

  function reset(): void {
    settings.value = { ...defaultSettings }

    writeSettings(settings.value)
  }

  return {
    enquiries,
    status,
    meta,
    detail,
    detailStatus,
    settings,
    load,
    retry,
    find,
    openDetail,
    closeDetail,
    setStage,
    update,
    reset,
  }
})
