# The enquiries screen

`/enquiries` is a list beside one enquiry's card, the third screen on the
contacts shape and the first that reads two endpoints.

- **The list is not a listbox, and that is the one thing here not to copy from
  contacts.** Business logic 5.1 puts a control inside the row, the stage pill,
  and an ARIA option may not contain interactive content: a button inside one
  is either flattened out of the accessibility tree or stops its parent being
  an option, and which a browser does is not ours to choose (decision 240).
  Worse, the pattern could never reach the pill anyway, because
  `aria-activedescendant` moves a virtual cursor and a control needs real
  focus. So it is a plain `role="list"` of `role="listitem"`, each row's main
  target a real link, the pill a real button whose click never reaches it, and
  a roving tabindex moving real focus. The filter field is an ordinary search
  input with no combobox role. `src/lib/enquiries.guards.test.ts` fails if a
  listbox, an option or an `aria-activedescendant` ever appears in the feature,
  because the next person building a list here will open `ContactList.vue`
  first.
- **Two requests, and they are two for different reasons.** `GET /api/enquiries`
  is one payload held whole in memory, with every sort, group and filter in the
  browser: no pagination, no virtualisation, no debounce and no spinner, which
  is what makes it work with no signal. `GET /api/enquiries/{id}` is a second
  request because it carries the original message, and five hundred pasted
  WhatsApp threads is not a list payload. **Opening a row draws the header from
  the list row it already has** and fills the rest in when the detail arrives;
  every field in 19.3's header is on the row, so there is no empty state and no
  spinner in between.
- **Three orders, and the default is neglect.** Staleness runs oldest-touched
  first in bands, with a pinned group of `new` above all of them, newest first
  (decision 236): a brand new enquiry has the freshest timestamp in the list and
  would sort to the bottom, which is exactly backwards, because it is the one
  nobody has looked at. Stage runs the pipeline, oldest-touched inside each.
  Wedding date runs soonest first. `lost` comes out before any of the three and
  goes back on the end under "Not going ahead", because left in it would sort
  into Gone quiet and read as work to do; the heading cannot be "Closed",
  which is taken by done and paid.
- **Gone quiet is not computed here.** A row is in that band when its
  `waiting_on` is `artist_enquiry_cold`, which the server resolved. The other
  two boundaries are fixed at two and eight days. The threshold never reaches
  the client, which is the point: it is one number read once, on the server, and
  this screen cannot disagree with the Home attention block about it. A test
  puts a forty-day-old row with a null `waiting_on` in a warmer band, which is
  the assertion a client-side threshold would fail.
- **Colour on the row means attention, not stage.** Warning and danger are
  reserved for the staleness figure and the clash line, and the stages take the
  quieter families: accent for New, because "nobody has looked at this" is the
  one stage that is a call to act, then neutral, info and success in pipeline
  order (decision 2026-09-06.1803). An earlier version had Possible on warning
  and it read as an alarm about a good thing, because `--warning-text` is
  `--color-warning-800` and reads red.
- **The stage pill IS the control** (decision 2026-09-06.1802). Tapping it opens
  a sheet with the four live stages, a rule, then Convert to booking and This
  one is not going ahead. Making the thing the eye already goes to tappable
  costs nothing on the row; a named advance chip beside it truncated five more
  second lines out of fourteen at 375px.
- **The ending is a second view inside the same sheet**, listing the nine
  reasons under two headings. The heading carries the side, so no reason has to
  name who did it, and the two rows both reading "Another reason" are not a
  duplication: which heading a reason sits under is the fact being recorded.
  Endings are two taps deliberately: 5.1 asks for one tap to Possible and
  nothing asks for one tap to an ending.
- **A sheet that swaps its own contents calls `refocus()`** (decision
  2026-09-06.1513). Going back from the reasons view hides the Back button, and
  if that button had focus, focus falls to the body and Escape silently stops
  closing the sheet. `useDialogBehaviour` exposes `refocus()` and
  `AnchoredSheet` hands it on, so the mechanism stays where finding the first
  focusable element already lives and the decision stays with the only thing
  that knows its content changed. A test asserts it, and fails without it.
- **The second line is always the date and the place, and "No date yet" is a
  first-class value.** A row that says nothing where a date goes reads as a bug
  in the app rather than as a fact about the wedding. The shortening is
  `src/lib/eventLine.ts`, shared with the contacts row, because those three
  rules were measured once at 375px. "and a trial" is appended from the
  payload's `has_trial`, which the row cannot work out from `event`: that
  carries the main day, so a booking with a trial in March and the wedding in
  May would otherwise look exactly like one with no trial.
- **`total_minor` null and zero are different facts and this screen is the
  first to render the difference.** No figure at all on a row nobody has
  priced, "No price yet" on its detail, and "£0" for a job somebody is doing
  for nothing.
- **The clash line describes the DATE, not the record.** It appears on a row
  whose own stage holds no calendar mark: an enquiry at `in_conversation`
  carries nothing per `strengthByStage`, and it still reports the confirmed
  booking and the two others on its Saturday. A deliberate departure from the
  calendar's rule. Five wordings, cut down because "Already booked, and two
  others want this date" truncates on every frame including the 400px column.
  It is not a warning and it prevents nothing.
- **The detail is the booking screen, not a second layout.** Business logic 4.3
  is one bookings table with a stage column, so there is no enquiry detail to
  keep in step with a booking one; what this renders is 19.3's header and
  summary against a record where most of it is empty. **A section appears when
  the stage makes it the next useful thing and carries the action when it is
  empty** (decision 2026-09-06.1806), and one sentence at the foot says what is
  still to come rather than nine empty headings pushing the five-in-the-morning
  summary below the fold.
- **The feature map is checked before the stage, and that is not an
  optimisation.** Business logic 21 and 6: with invoicing off nothing is ever
  waiting on a deposit, so a section for a switched-off feature is never drawn
  at any stage. Gating the other way round would draw one the moment a record
  converted. `src/lib/enquirySections.ts` owns both rules and is tested without
  mounting anything.
- **Five view settings on the device**, in one localStorage key: the sort, and
  switches for the source line, the quoted totals, the clash line and the
  archive. The mechanism is `src/lib/viewSettings.ts`, shared with contacts;
  none of the values is. Five is close to the limit for one menu and a sixth
  needs an argument.
- **Both panels are `AnchoredSheet`, both `align="right"`**, because the view
  button sits at the right of the list column and so does the stage pill on a
  row.

Not built yet on that screen: the price flow behind "Build a price", adding an
enquiry, and the follow-up reminder from 5.6.4. Deliberately not built:
bulk actions with a checkbox column, saved filters, replying from the list and
source analytics, all of which are what a list like this grows by default and
which thirty rows do not need.
