# The bookings screen

`/bookings` is a month calendar and a list, two views of one set of events on
one screen, per business logic 19.1. It was the first screen built against a
seam rather than against the API, and it reads the API now:
`src/lib/bookings.ts` is its data module, sitting where `src/lib/auth.ts`
sits: below the store, above `api.ts`, and the only other file that turns a
URL into a domain shape. It exports `events({ from, to })` and
`eventMonths()`, both unwrapping the `data` envelope.

**The unit is an event, not a booking.** A booking is one record at a stage
and its dates live in `events`, normally a trial and a `main`, so a row in the
list and a mark on a day are both an event carrying its booking's stage,
client and total. `src/types/bookings.ts` is that view model in **snake_case**,
matching the API and `src/types/auth.ts` before it, and every field in it is a
column in `docs/database-schema.md`: the wedding day is `main` and there is no
`wedding` type, money is `total_minor` beside its `currency`, and
`last_touched_at` is the UTC instant rather than a day count, which would go
stale in an open tab. **Timestamps are parsed, never compared as strings**: the
API sends microseconds, which is Laravel's correct ISO 8601, and the same
moment can be written more than one way.

- **The store holds a list of loaded ranges, not one span.** The first load
  asks from the first of the current month forward, so a normal session makes
  one call and never another. It sends `from` rather than letting the API
  default to today, because the calendar opens on the current month and that
  month starts before today: with the default, a Saturday already worked would
  draw as empty, which is a lie about the artist's own diary rather than a gap
  in a feature. The default stays right for every other caller. Moving to a month outside
  what is held fetches that month with a month either side and merges by event
  id. The list of ranges is not tidiness: the jump sheet advertises every
  month the account has ever worked, and a contiguous backfill from today to
  January 2020 is past the API's span cap, so it would be refused and the month
  the artist asked for would never load.
- **The range guard lives in the store, and it is asked about the month, not
  the window fetched around it.** The scroll sync changes the month as the
  artist scrolls, so `ensureMonthLoaded` is called constantly and must be free
  when there is nothing to do. Testing the padded window instead puts its start
  before today, nothing ever looks loaded, and scrolling forward fires a
  request per month.
- **A window failure never clears the list.** `status` covers the first load
  and `windowStatus` a window, so a month that would not load is a month with
  no marks, which is recoverable, rather than an empty screen, which is not.

- **A mark answers one question: is this day spoken for.** So a stage that no
  longer holds the date carries nothing. `confirmed`, `completed` and `closed`
  are a filled circle, `provisional` is a ring, `possible` and `quoted` are a
  count badge that appears *alongside* either rather than instead of one, and
  `new`, `in_conversation`, `lost` and `cancelled` carry no mark at all.
  `in_conversation` has none because business logic 5.1 puts the soft hold at
  Possible, and cancelled has none because the date is free again. The three
  differ by shape before they differ by colour, because roughly one man in
  twelve cannot separate them by hue. Strength is computed in
  `src/lib/dayMarks.ts` and never stored, per schema section 8.
- **`MonthGrid.vue` has never heard of a booking.** It takes a month, a marks
  map keyed `'YYYY-MM-DD'`, a selected day and a density, and emits a date.
  That is what would let it draw availability or blocked-out days later: a
  caller with a different idea of what a day means builds a different map.
- **The grid is built by walking calendar dates, never by adding 24 hours,**
  and a day key is `format(d, 'yyyy-MM-dd')`, never `toISOString()`, which is
  UTC and would file an evening event under the previous day for the eight
  months the clocks are forward. `src/lib/monthGrid.ts` is the only place
  either happens, and the guard test bans `toISOString` everywhere in the
  feature except the fixtures, where it serialises a UTC instant and is
  correct. **The obvious version of the DST test cannot fail**: both British
  clock changes are on a Sunday, so with Monday-first weeks they are always
  the last day of their week, and a naive build anchored at midnight survives
  the spring forward too. It is the October month grid that breaks, repeating
  the 25th, and that is the assertion carrying the weight.
- A month renders the four, five or six rows it actually needs and is never
  padded to 42 cells. On a 375px phone a row saved is 49px, which is most of
  another booking on screen.
- **The two halves sync in one direction only.** Scrolling the list moves the
  calendar; nothing the calendar does ever scrolls the list, and anything the
  calendar drives holds the sync off for 450ms so the two cannot chase each
  other. The scroll handler changes the month and nothing else, so the list's
  props do not change and Vue leaves it alone: a full re-render would rebuild
  the list and throw away the scroll position the handler is reading from.
  The sync is off in week mode, because a seven-day strip cannot meaningfully
  follow a list spanning three months.
- **The month's height is animated by a watcher, not by the caller.** There
  are five ways to change the month, the arrows, the swipe, a day in the
  padding, the jump sheet and Today, and the first version wrapped only the
  one that went through the tap handler.
- The layout switches on a **container query** at `--container-split`, not a
  media query, so the calendar is a band above the list below it and a column
  beside it above. The container is `<main>`, which is the viewport minus the
  sidebar once the sidebar appears, so at 1024px the screen is stacked with a
  688px container: the calendar therefore carries a maximum width, or it draws
  97px cells and pushes the list off the bottom.
- **The list's group headings rest under the calendar, not behind it.** Both
  are sticky, so on a phone, where the calendar is a band across the top, a
  heading pinned to the same line is invisible. The list wrapper sets
  `--stick-offset` from a measurement, and the test is geometric rather than a
  second copy of the breakpoint: if the calendar's right edge is left of the
  list they are side by side and the offset is zero. Each band is also its own
  list item wrapping its own rows, because a sticky element is bounded by its
  containing block, and in one flat list every heading pins to the top of the
  page at once and only paint order decides which is visible.
- **`MonthJumpSheet.vue` is the year strip and the month grid, and nothing
  else.** The panel around it is `ui/AnchoredSheet.vue`, aligned left because
  the month title sits near the left of the calendar, so the panel grows
  rightwards away from it. What stays in the file is content: which years the
  strip offers, which month is shown, and centring the strip on the current
  year when it opens.

Not built yet on that screen: the Upcoming, Past and All tabs and the status
filter from 19.2, the clash warning from 5.2, and the booking detail screen.
