# The home screen

`/home` is business logic section 18's three blocks in one payload, and
`/attention` is the second of them without its cap. `docs/home-poc.html` is the
prototype both were built against.

- **Next up, Attention, Money, and the artist reorders them in Adjust.** Next up
  leads on a tone judgement rather than a measurement: the first thing an artist
  meets should be the next two weddings, not a list of things they have not
  done. Both arrangements were measured and both put something from every block
  above the fold once Attention is capped, so once both work the pleasant one
  wins. **Blocks do not rearrange themselves**: the order is fixed and blocks
  drop out of it, because a screen that rearranges depending on how the week is
  going is a screen nobody can learn.
- **The empty-block rule.** Attention and Next up disappear when empty. **Money
  never does**, and it is never removed by a feature toggle either (decision
  2026-09-06.1946): 21.2 says a toggle removes items from *the attention block*,
  named, and a booking carries a price whether or not anybody raised an invoice
  for it. What the toggles take away is the cash half, one figure at a time.
- **`/attention` is a route and not a view flag** (decision 2026-09-06.1942): a
  notification can link straight to it, the back gesture works, and a flag
  cannot be linked to. It costs **one line in `navigation.ts`'s `sectionKey`
  map**, `attention: 'home'`, with `booking: 'bookings'` as the precedent. That
  line is the only thing between correct and silently broken: without it
  `activeTabIndex` is minus one and the tab bar's pill hides with no error
  anywhere, which is why it has a test.
- **The attention list is one component rendered TWICE on /home**, capped and
  uncapped, with the container query choosing. The reason is not the cap: it is
  that **the band headings differ in TEXT rather than in visibility.** "2 need
  you" below the split and "4 need you" above it cannot be produced by clipping
  one rendered list, so no amount of CSS on a single render works. It is also
  what the prototype measured. Anybody tempted to collapse it to one render
  should read that sentence rather than the duplication.
- **jsdom evaluates no container queries**, so both copies are in the DOM under
  test and `@split:hidden` hides neither. Row counts and band headings are
  therefore asserted in `AttentionList.test.ts` with an explicit limit, where
  there is one of everything, and `HomeView.test.ts` asserts only which blocks
  and which wrappers exist. A row count on the mounted screen would see double
  and could pass while the screen is wrong.
- **The cut is made on the array's order and the grouping happens after it.**
  The endpoint returns decision 217's precedence, so the first N are the N most
  urgent. Grouping first and cutting each group gives four of the artist's own
  rows and no client group at all, so an overdue balance can never reach the
  preview. That was a real bug found by building the prototype and the order of
  those two steps in `attentionGroups()` is the whole fix.
- **The band headings count what is drawn; "See all N" carries the real
  total.** A heading reading "4 need you" above two rows reads as a broken list,
  and a preview quietly showing four of eight would be the amounts-owed switch
  problem on the screen where it would hurt most. There is no second See all
  under the rows: two links to the same place four rows apart reads as a
  mistake.
- **The DOM order is the artist's order.** An earlier version fixed the DOM and
  moved blocks with CSS `order`, which reads correctly and tabs wrongly: focus
  order and screen-reader order follow the DOM, so an artist who put Money first
  would hear Attention first. It is grid placement instead, with
  `home-columns` in `app.css` for the two columns, because
  `styleRules.test.ts` bans an arbitrary value in a component and the rail's
  width wants one home.
- **The row is a link and not a `<button>`** (decision 240), even though nothing
  on it is interactive yet, so the two inline writes can be added beside it
  rather than rebuilding the markup.
- **Neither inline write is built, and that is the recommendation rather than a
  deferral** (decision 2026-09-06.2350). Decision 27's argument is that an
  artist who cannot stop the chasers marks the invoice paid and the earnings
  figures quietly become wrong; **a Snooze that visibly does nothing causes the
  harm it was designed to prevent.** A test asserts no row carries an action,
  named against the two values that will get one, so the day they land it says
  where. Decision 2026-09-06.1950's measurement was taken with a button on all
  eight rows and says nothing about two on two: **re-measure at 375px when the
  writes land.**
- **The only thing that wears a colour is money that is genuinely late.** Two
  things were drawn and removed for this and both would be added back by
  somebody who had not watched them fail: a coloured dot per row, which the band
  heading two lines above already says, and a call time in warning colour, which
  calls an early start a fault. `--warning-text` reads red.
- **Both lines are built here and the API sends no copy.** Every day count is
  derived at render with `differenceInCalendarDays` against **`meta.today`, the
  account's day, not the device's**: the server already decided what is overdue
  using that day, so a phone in another timezone doing its own arithmetic would
  put a number on a row that the money block disagrees with. The device's day is
  the fallback and is only reachable before the first response.
- **A deposit that is not paid is not necessarily overdue.** The resolver's
  deposit branch fires as soon as one is uncovered, without waiting for a due
  date, so there are two sentences and `sentenceKey()` picks by whether it is
  actually late. And the day lines pluralise on three forms, because "Arrived 0
  days ago" on the morning something lands reads as a bug rather than as today.
- **The venue is not on any attention row** (decision 2026-09-06.1956): it was
  on the money rows and is what pushed five of eight second lines over at 375px.
- **Next up sends six and draws three**, which is the division of labour the
  endpoint intends. Measured rather than assumed: a row is about a hundred
  pixels at 375px, so six fill the screen alone and push every attention row
  below the fold. **The party is drawn on main events only**, because
  `party_size` is the whole booking's party and on a trial it would say "Bride
  and 6" for an appointment that is usually one person. The venue is shortened
  by `eventLine.ts`'s `venueShort`, shared with the two list screens rather than
  written a third time.
- **Two traps in the money object, both additions waiting to be made by
  mistake.** `outstanding.snoozed_minor` is a **subset** of `overdue_minor`, not
  a third bucket: due plus overdue is the whole of outstanding. And `owed_minor`
  and `overdue_minor` answer different questions — owed is balances past their
  date on weddings that have happened, overdue also counts unpaid deposits — so
  they are never drawn as a figure and its total. `snoozed_minor` sits under the
  headline and only above zero, because an escape hatch that silently shrinks a
  figure teaches artists to distrust the figure.
- **`MoneyBlock` takes no feature flags at all**, which is the strongest form of
  "reads the response and never the auth store": the payload's own nulls carry
  the toggles, so there is nothing to consult a store about. The period selector
  governs three figures and not five, and the line under the grid says so.
- **Adjust is two settings and must not grow a third.** A switch that turns a
  block off is a control that hides an unheld Saturday, which is the amounts-owed
  problem on the screen where it would hurt most. The money period lives on the
  block and not here: one value with two homes is two places to keep in step.
  It is `AnchoredSheet`'s fifth caller.
- **The reorder works by keyboard as well as by drag**, because a reorder that
  only works by dragging is one half the people cannot do. `preventDefault` on
  `pointerdown` stops the drag becoming a text selection and also stops the
  handle taking focus, so focus is given back by hand; without it the keyboard
  half is dead after any tap. `v-for` with a stable key moves the DOM nodes
  rather than recreating them, so the handle somebody is holding survives.
- **`permutationOf` joined `src/lib/viewSettings.ts`** rather than living beside
  this screen's settings: it is the same category as `oneOf`, and a permutation
  cannot be checked as a list of allowed values, because three known keys with a
  duplicate would render one block three times and lose two. The three
  `*View.ts` files stay three: the mechanism is shared and no value is.
- **The quiet week draws one line, "Nothing needs you today"**, rather than
  nothing at all (decision 2026-09-06.1948). An artist used to seeing four
  things who suddenly sees none cannot tell a clear week from a bug. The empty
  account is a different state: every block empty and every figure nought, so
  the screen is a first-run state instead of three empty blocks.

Not built on that screen: the two inline writes, which need endpoints that do
not exist; Export, which points at Settings for now; and the first-run buttons,
which open the create sheet because neither creating a booking nor creating an
enquiry is a screen yet. Deliberately not built: search (18.4, which wants
decision 102's projection table), the notification bell (decision 106, open),
and live travel time on a Next up row.
