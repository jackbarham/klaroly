import { describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import {
  agoKey,
  clashLine,
  daysSince,
  groupEnquiries,
  isCold,
  matches,
  whenAndWhere,
} from '@/lib/enquiryList'
import type { Enquiry } from '@/types/enquiries'

// The rules the enquiries list runs on. Each one is a plain function taking
// plain values, so none of this mounts anything: what is being tested is the
// rule, not a component's ability to call it.

const t = (key: string) => i18n.global.t(key)

const today = new Date(2026, 8, 6)

// An instant this many whole days before the test's today, at midday so no
// assertion turns on the hour.
function daysAgo(days: number): string {
  const when = new Date(2026, 8, 6, 12, 0, 0)

  when.setDate(when.getDate() - days)

  return when.toISOString()
}

function enquiry(over: Partial<Enquiry> = {}): Enquiry {
  return {
    id: 1,
    stage: 'possible',
    client_name: 'Imogen Hartwell',
    contact_id: 10,
    source: 'web_form',
    source_booking: null,
    last_touched_at: daysAgo(3),
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

function keysOf(groups: ReturnType<typeof groupEnquiries>): string[] {
  return groups.map((group) => group.key)
}

function idsOf(groups: ReturnType<typeof groupEnquiries>): number[] {
  return groups.flatMap((group) => group.enquiries.map((item) => item.id))
}

describe('the staleness figure', () => {
  it('counts whole calendar days rather than elapsed hours', () => {
    // Touched at eleven last night, read this morning: that is Yesterday, not
    // Today until eleven.
    expect(daysSince(new Date(2026, 8, 5, 23, 0, 0).toISOString(), today)).toBe(1)
  })

  it('never goes negative on a clock that is a little ahead', () => {
    expect(daysSince(new Date(2026, 8, 7).toISOString(), today)).toBe(0)
  })

  // The bands widen as they get older because precision stops mattering: the
  // difference between eleven and twelve days is worth having and the
  // difference between six and seven weeks is not.
  it('says it in words, and widens as it ages', () => {
    expect(agoKey(0)).toEqual({ key: 'enquiries.ago.today', count: 0 })
    expect(agoKey(1)).toEqual({ key: 'enquiries.ago.yesterday', count: 1 })
    expect(agoKey(11)).toEqual({ key: 'enquiries.ago.days', count: 11 })
    expect(agoKey(21)).toEqual({ key: 'enquiries.ago.weeks', count: 3 })
    expect(agoKey(42)).toEqual({ key: 'enquiries.ago.weeks', count: 6 })
    expect(agoKey(90)).toEqual({ key: 'enquiries.ago.months', count: 3 })
  })
})

/**
 * Gone quiet is the server's answer, not this screen's.
 *
 * The threshold is an account setting resolved once inside
 * App\Services\WaitingOnResolver and it never reaches the client. The home
 * screen's attention block asks the same resolver, so the two cannot disagree
 * about which enquiries have gone quiet.
 */
describe('gone quiet', () => {
  it('reads waiting_on', () => {
    expect(isCold(enquiry({ waiting_on: 'artist_enquiry_cold' }))).toBe(true)
    expect(isCold(enquiry({ waiting_on: 'artist_price' }))).toBe(false)
  })

  /**
   * The one that would fail against a client-side threshold.
   *
   * Forty days untouched is past any threshold anybody would choose, and this
   * row is not cold because the server did not say so. A screen computing its
   * own answer would put it in Gone quiet and disagree with the home screen.
   */
  it('leaves a forty-day-old row out of the cold band when the server has not said so', () => {
    const groups = groupEnquiries([
      enquiry({ id: 1, last_touched_at: daysAgo(40), waiting_on: null }),
    ], 'staleness', today, false)

    expect(keysOf(groups)).toEqual(['over_a_week'])
  })

  // And the pair: the same row, with the server's answer on it.
  it('puts the same row in the cold band when the server has', () => {
    const groups = groupEnquiries([
      enquiry({ id: 1, last_touched_at: daysAgo(40), waiting_on: 'artist_enquiry_cold' }),
    ], 'staleness', today, false)

    expect(keysOf(groups)).toEqual(['cold'])
    expect(groups[0].cold).toBe(true)
  })
})

describe('the staleness order', () => {
  /**
   * A brand new enquiry has the freshest timestamp in the list and would sort
   * to the bottom, which is exactly backwards: it is the one nobody has looked
   * at. So New is pinned above everything and ordered newest first, and
   * staleness orders the rest.
   */
  it('pins New above everything, newest first', () => {
    const groups = groupEnquiries([
      enquiry({ id: 1, stage: 'quoted', last_touched_at: daysAgo(30), waiting_on: 'artist_enquiry_cold' }),
      enquiry({ id: 2, stage: 'new', last_touched_at: daysAgo(4) }),
      enquiry({ id: 3, stage: 'new', last_touched_at: daysAgo(1) }),
    ], 'staleness', today, false)

    expect(keysOf(groups)).toEqual(['unseen', 'cold'])
    expect(idsOf(groups)).toEqual([3, 2, 1])
  })

  // The other two boundaries are fixed at two and eight days and need no
  // setting at all.
  it('bands the rest at two and eight days, oldest first', () => {
    const groups = groupEnquiries([
      enquiry({ id: 1, last_touched_at: daysAgo(0) }),
      enquiry({ id: 2, last_touched_at: daysAgo(1) }),
      enquiry({ id: 3, last_touched_at: daysAgo(2) }),
      enquiry({ id: 4, last_touched_at: daysAgo(7) }),
      enquiry({ id: 5, last_touched_at: daysAgo(8) }),
      enquiry({ id: 6, last_touched_at: daysAgo(30) }),
    ], 'staleness', today, false)

    expect(keysOf(groups)).toEqual(['over_a_week', 'this_week', 'recent'])
    expect(idsOf(groups)).toEqual([6, 5, 4, 3, 2, 1])
  })
})

describe('the stage order', () => {
  it('runs the pipeline, oldest touched first inside each stage', () => {
    const groups = groupEnquiries([
      enquiry({ id: 1, stage: 'quoted', last_touched_at: daysAgo(1) }),
      enquiry({ id: 2, stage: 'new', last_touched_at: daysAgo(2) }),
      enquiry({ id: 3, stage: 'quoted', last_touched_at: daysAgo(20) }),
      enquiry({ id: 4, stage: 'in_conversation', last_touched_at: daysAgo(5) }),
      enquiry({ id: 5, stage: 'possible', last_touched_at: daysAgo(9) }),
    ], 'stage', today, false)

    expect(keysOf(groups)).toEqual(['stage-new', 'stage-in_conversation', 'stage-possible', 'stage-quoted'])
    // The row at the top of Quoted is the quote nobody has chased.
    expect(idsOf(groups)).toEqual([2, 4, 5, 3, 1])
  })
})

describe('the wedding date order', () => {
  it('groups this month, the next three and later, soonest first', () => {
    const dated = (id: number, date: string) => enquiry({ id, event: { ...enquiry().event!, date } })

    const groups = groupEnquiries([
      dated(3, '2027-06-01'),
      dated(1, '2026-09-20'),
      dated(2, '2026-11-15'),
    ], 'date', today, false)

    expect(keysOf(groups)).toEqual(['this_month', 'next_three', 'later'])
    expect(idsOf(groups)).toEqual([1, 2, 3])
  })

  // An enquiry with no date is a real and often promising enquiry, so it goes
  // last rather than being dropped, and inside the band it is ordered by
  // neglect so the group is useful rather than arbitrary.
  it('puts the undated last, ordered by neglect', () => {
    const groups = groupEnquiries([
      enquiry({ id: 1, event: null, last_touched_at: daysAgo(2) }),
      enquiry({ id: 2, event: null, last_touched_at: daysAgo(20) }),
      enquiry({ id: 3 }),
    ], 'date', today, false)

    expect(keysOf(groups)).toEqual(['later', 'no_date'])
    expect(idsOf(groups)).toEqual([3, 2, 1])
  })
})

describe('the ones not going ahead', () => {
  // Left in, a lost enquiry would sort into Gone quiet and read as work to do,
  // which is the opposite of what it is.
  it('are hidden by default', () => {
    const groups = groupEnquiries([
      enquiry({ id: 1 }),
      enquiry({ id: 2, stage: 'lost', last_touched_at: daysAgo(60) }),
    ], 'staleness', today, false)

    expect(idsOf(groups)).toEqual([1])
  })

  it('go last in their own group in every order', () => {
    const rows = [
      enquiry({ id: 1, stage: 'new', last_touched_at: daysAgo(1) }),
      enquiry({ id: 2, stage: 'lost', last_touched_at: daysAgo(60) }),
      enquiry({ id: 3, stage: 'quoted', last_touched_at: daysAgo(9) }),
    ]

    for (const sort of ['staleness', 'stage', 'date'] as const) {
      const groups = groupEnquiries(rows, sort, today, true)

      expect(groups[groups.length - 1].key).toBe('lost')
      expect(idsOf(groups)[2]).toBe(2)
    }
  })
})

describe('the second line', () => {
  // A row that says nothing where a date goes reads as a bug in the app rather
  // than as a fact about the wedding.
  it('says No date yet rather than nothing', () => {
    expect(whenAndWhere(enquiry({ event: null }), today, t)).toBe('No date yet')
  })

  it('drops the year when it is this year and keeps it when it is not', () => {
    const dated = (date: string) => enquiry({ event: { ...enquiry().event!, date } })

    expect(whenAndWhere(dated('2026-09-20'), today, t)).not.toContain('2026')
    expect(whenAndWhere(dated('2027-05-29'), today, t)).toContain('2027')
  })

  it('takes the venue up to its first comma', () => {
    const line = whenAndWhere(enquiry({
      event: { ...enquiry().event!, venue_name: 'Corvel Hall, Little Hadham, Hertfordshire' },
    }), today, t)

    expect(line).toContain('Corvel Hall')
    expect(line).not.toContain('Hertfordshire')
  })

  /**
   * The trial, which the row cannot work out from the date it is shown by: a
   * booking with a trial in March and the wedding in May carries the wedding
   * in `event` and would otherwise look exactly like one with no trial.
   */
  it('says there is a trial as well as the main day', () => {
    expect(whenAndWhere(enquiry({ has_trial: true }), today, t)).toContain('and a trial')
  })

  // Paired, so "and a trial" cannot pass by being on every row.
  it('says nothing about a trial when there is not one', () => {
    expect(whenAndWhere(enquiry(), today, t)).not.toContain('and a trial')
  })

  // A standalone trial is shown BY its trial, so the line already names it and
  // adding "and a trial" would have it say the same thing twice.
  it('does not add it when the shown event is itself the trial', () => {
    const line = whenAndWhere(enquiry({
      has_trial: true,
      event: { ...enquiry().event!, type: 'trial' },
    }), today, t)

    expect(line).toContain('Trial')
    expect(line).not.toContain('and a trial')
  })
})

/**
 * The clash line's five wordings.
 *
 * It is not a warning and it prevents nothing. A Saturday with four enquiries
 * on it is a Saturday where three of them will book somebody else within a
 * fortnight, and the one rung today is the one that converts.
 */
describe('the clash line', () => {
  const say = (line: ReturnType<typeof clashLine>) => (line === null ? null : i18n.global.t(line.key, line.count))

  it('reads all five ways', () => {
    expect(say(clashLine({ confirmed: 1, provisional: 0, others: 2 }))).toBe('Booked, and 2 others want it')
    expect(say(clashLine({ confirmed: 1, provisional: 0, others: 0 }))).toBe('You are already booked')
    expect(say(clashLine({ confirmed: 0, provisional: 1, others: 1 }))).toBe('Pencilled in, and one other wants it')
    expect(say(clashLine({ confirmed: 0, provisional: 1, others: 0 }))).toBe('Pencilled in for someone else')
    expect(say(clashLine({ confirmed: 0, provisional: 0, others: 2 }))).toBe('2 others want this date')
    expect(say(clashLine({ confirmed: 0, provisional: 0, others: 1 }))).toBe('One other wants this date')
  })

  it('says nothing when the date carries nothing else', () => {
    expect(clashLine(null)).toBeNull()
    expect(clashLine({ confirmed: 0, provisional: 0, others: 0 })).toBeNull()
  })

  /**
   * The deliberate departure from the calendar's rule.
   *
   * An enquiry at in_conversation carries no calendar mark, per
   * strengthByStage in src/lib/dayMarks.ts, and it still reports what is
   * already on its Saturday, because the line describes the DATE rather than
   * this record's contribution to it. The API is what decides that, and this
   * asserts the screen does not undo it.
   */
  it('is drawn on a row whose own stage holds no calendar mark', () => {
    const line = clashLine(enquiry({
      stage: 'in_conversation',
      clash: { confirmed: 1, provisional: 0, others: 2 },
    }).clash)

    expect(say(line)).toBe('Booked, and 2 others want it')
  })
})

describe('the filter', () => {
  it('matches the name, folded, so an unaccented query finds an accented one', () => {
    expect(matches(enquiry({ client_name: 'Saoirse Brontë' }), 'bront', t)).toBe(true)
  })

  it('matches the venue as the row shows it', () => {
    expect(matches(enquiry(), 'marlbrook', t)).toBe(true)
  })

  // "Quoted" typed into a list ordered by neglect is a genuinely useful
  // narrowing, and "voice" finds the ones that arrived as a voice note even
  // when the source line is switched off.
  it('matches the stage and the source by their words', () => {
    expect(matches(enquiry({ stage: 'quoted' }), 'quoted', t)).toBe(true)
    expect(matches(enquiry({ stage: 'in_conversation' }), 'conversation', t)).toBe(true)
    expect(matches(enquiry({ source: 'voice_note' }), 'voice', t)).toBe(true)
  })

  it('matches nothing it should not', () => {
    expect(matches(enquiry(), 'hedsor', t)).toBe(false)
  })

  it('lets everything through when the box is empty', () => {
    expect(matches(enquiry(), '   ', t)).toBe(true)
  })
})
