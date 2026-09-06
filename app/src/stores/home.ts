import { computed, ref } from 'vue'
import { parseISO } from 'date-fns'
import { defineStore } from 'pinia'
import { home as fetchHome } from '@/lib/home'
import { defaultSettings, readSettings, writeSettings, type HomeViewSettings } from '@/lib/homeView'
import type { HomeMeta, HomeSummary } from '@/types/home'

// The home screen's one payload and how this device likes to read it.
// Components read this store and never call the data layer themselves.
//
// **One request for three blocks**, which makes the screen one thing to cache
// and is what business logic 23.2 asks of the screen that most has to work with
// no signal. There is no pagination, no filter and no spinner inside a block:
// the whole summary arrives or it does not.

export type LoadStatus = 'idle' | 'loading' | 'ready' | 'failed'

export const useHomeStore = defineStore('home', () => {
  const summary = ref<HomeSummary | null>(null)
  const meta = ref<HomeMeta | null>(null)
  const status = ref<LoadStatus>('idle')

  // Read once, when the store is created, so the first render is already in the
  // shape this person left it in and no block is seen to move after the screen
  // has drawn. readSettings copes with a storage that throws.
  const settings = ref<HomeViewSettings>(readSettings())

  /**
   * Fetches once. Coming back to Home from another tab does not refetch, and
   * two components mounting together do not fetch twice.
   */
  async function load(): Promise<void> {
    if (status.value === 'loading' || status.value === 'ready') {
      return
    }

    status.value = 'loading'

    try {
      const answer = await fetchHome()

      summary.value = answer.summary
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

  /**
   * The day every count on this screen is worked out against.
   *
   * **The account's day, from meta.today, and not the device's.** The server
   * already decided what is overdue using that day, so a phone in another
   * timezone doing its own arithmetic would put "8 days late" beside a figure
   * the server built from nine. The artist and her accountant are on one
   * calendar and the phone in her hand is not necessarily on it.
   *
   * The device's day is the fallback, and it is only reachable before the first
   * response has arrived or after one that failed, when there is nothing on
   * screen to be inconsistent with. A malformed meta.today would fall here too;
   * parseISO returns an invalid date rather than throwing, so it is checked
   * rather than trusted.
   */
  const today = computed(() => {
    const stamp = meta.value?.today

    if (stamp === undefined) {
      return new Date()
    }

    const parsed = parseISO(stamp)

    return Number.isNaN(parsed.getTime()) ? new Date() : parsed
  })

  // The count before the cap, which is what "See all 8" says. A preview that
  // quietly showed four of eight would be the amounts-owed switch problem on
  // the screen where it would hurt most.
  const attentionTotal = computed(() => meta.value?.attention.total ?? 0)

  /**
   * Whether this account has nothing at all, which is the first-run state
   * rather than three empty blocks.
   *
   * It is not "the attention list is empty": a quiet week has bookings and
   * money and simply nothing waiting, and that draws the all-clear line. This
   * is an artist who has not put anything in yet, so every block is empty and
   * the money figures are all nought.
   */
  const isEmptyAccount = computed(() => {
    const found = summary.value

    if (found === null) {
      return false
    }

    return found.attention.length === 0
      && found.upcoming.length === 0
      && found.money.booked_ahead_minor === 0
      && found.money.provisional_minor === 0
      && (found.money.owed_minor ?? 0) === 0
      && Object.values(found.money.periods).every((period) => period.booking_count === 0)
  })

  // A patch rather than a whole object, because Adjust changes one setting at a
  // time and stays open while it does, so the screen redraws underneath and the
  // setting is judged by its effect rather than by its name.
  function update(patch: Partial<HomeViewSettings>): void {
    settings.value = { ...settings.value, ...patch }

    writeSettings(settings.value)
  }

  function reset(): void {
    settings.value = { ...defaultSettings }

    writeSettings(settings.value)
  }

  return {
    summary,
    meta,
    status,
    settings,
    today,
    attentionTotal,
    isEmptyAccount,
    load,
    retry,
    update,
    reset,
  }
})
