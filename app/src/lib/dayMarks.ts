import { computed, toValue, type MaybeRefOrGetter } from 'vue'
import type { BookingEvent, BookingStage } from '@/types/bookings'

// What each day of the calendar is wearing, keyed 'YYYY-MM-DD'.
//
// Mark strength is computed here and never stored, per schema section 8. The
// grid takes the map this produces and never sees a booking, which is what
// keeps MonthGrid presentational and lets the same component draw availability
// or blocked-out days later.

// A mark answers one question: is this day spoken for. So a stage that no
// longer holds the date carries nothing, and a stage that holds it or once
// held it carries the strength it held.
//
//   confirmed, completed, closed   the work is or was on. Filled.
//   provisional                    pencilled in. Ring.
//   possible, quoted               the soft hold from business logic 5.1.
//                                  Count badge.
//   new, in_conversation           an enquiry before the soft hold begins.
//                                  5.1 is explicit that the hold starts at
//                                  Possible, and moving an enquiry there is
//                                  the interaction the whole feature turns on.
//   lost, cancelled                the date is released and must never show
//                                  as taken. Cancelled in particular has to
//                                  vanish, or it would warn against a day the
//                                  artist is free to work.
const strengthByStage: Record<BookingStage, MarkStrength | null> = {
  new: null,
  in_conversation: null,
  possible: 'possible',
  quoted: 'possible',
  provisional: 'provisional',
  confirmed: 'confirmed',
  completed: 'confirmed',
  closed: 'confirmed',
  lost: null,
  cancelled: null,
}

export type MarkStrength = 'confirmed' | 'provisional' | 'possible'

// Highest first, because a day takes the strongest mark present and shows the
// enquiry count alongside it rather than instead of it. That is the case
// business logic 19.1 calls out: one confirmed booking and three live
// enquiries on the same Saturday have to be visible at once.
const rank: Record<MarkStrength, number> = {
  possible: 1,
  provisional: 2,
  confirmed: 3,
}

// Everything the grid needs to draw one day, and nothing else. There is no
// booking in this shape, which is what lets MonthGrid stay presentational and
// what would let it draw availability or blocked-out days later without
// learning a second vocabulary.
export interface GridMark {
  // The strongest thing on the day, which is what the circle draws.
  strength: MarkStrength
  confirmed: number
  provisional: number
  // Enquiries at Possible or Quoted, which is what the badge counts.
  possible: number
}

export interface DayMark extends GridMark {
  // Everything on the day, strongest first, including the stages that carry no
  // mark: tapping the day lists all of it.
  events: BookingEvent[]
}

export type DayMarks = Map<string, DayMark>

export function marksFor(events: BookingEvent[]): DayMarks {
  const marks: DayMarks = new Map()

  for (const event of events) {
    let mark = marks.get(event.date)

    if (!mark) {
      // strength is filled in below, once every event on the day has been
      // counted. Starting it at the weakest is what makes that a max.
      mark = { strength: 'possible', confirmed: 0, provisional: 0, possible: 0, events: [] }
      marks.set(event.date, mark)
    }

    mark.events.push(event)

    const strength = strengthByStage[event.stage]

    if (strength !== null) {
      mark[strength] += 1
    }
  }

  for (const [key, mark] of marks) {
    // A day whose events all carry no mark, a lone lost enquiry or a cancelled
    // booking, is not a marked day at all. It still has events on it, and
    // tapping it still lists them, but the grid must show nothing.
    if (mark.confirmed === 0 && mark.provisional === 0 && mark.possible === 0) {
      marks.delete(key)

      continue
    }

    mark.strength = mark.confirmed > 0
      ? 'confirmed'
      : mark.provisional > 0 ? 'provisional' : 'possible'

    mark.events.sort((a, b) => strengthOrder(b) - strengthOrder(a))
  }

  return marks
}

function strengthOrder(event: BookingEvent): number {
  const strength = strengthByStage[event.stage]

  return strength === null ? 0 : rank[strength]
}

export function useDayMarks(events: MaybeRefOrGetter<BookingEvent[]>) {
  const marks = computed(() => marksFor(toValue(events)))

  // 'YYYY-MM' for every month that has any event in it, marked or not, which
  // is what puts a dot on the month jump sheet. An artist's diary is mostly
  // empty with clusters on summer Saturdays, so "where is my work" is a more
  // common question than "take me to March".
  const monthsWithWork = computed(() => {
    const months = new Set<string>()

    for (const event of toValue(events)) {
      months.add(event.date.slice(0, 7))
    }

    return months
  })

  return { marks, monthsWithWork }
}
