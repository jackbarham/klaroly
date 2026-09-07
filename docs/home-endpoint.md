# The home endpoint

`GET /api/home` is what the home screen reads: business logic section 18's
three blocks in one payload. `App\Http\Controllers\HomeController` serves it,
and `docs/home-poc.html` is the prototype it was built against.

- **One route rather than three, and the reason is not caching.** Decision
  2026-09-06.1954 makes the owed headline the sum of the attention block's
  `client_balance` rows, so three routes would mean either the money one
  recomputing every booking's waiting-on state to produce one number, or the
  client summing the rows itself and holding the definition of a money figure
  in the front end. That Home is the screen which most has to work with no
  signal (business logic 23.2) is the second reason and not the first.
- **The cost is the thing to be careful about, and it is worse here than on the
  enquiries list.** That one asks the waiting-on axis of a list filtered to the
  enquiry stages; this asks it of every live booking on the account. Measured
  naive: **201 queries for forty live bookings.** The eager load is the
  enquiries one plus `contact`, and the two hops that always look redundant are
  needed for the third time, `lines.booking` and `invoices.payments.booking`.
  `HomeQueryCountTest` pins the whole endpoint at **34 queries**, asserted both
  as a literal and as identical at three bookings and at forty. **Two
  assertions, because they catch different failures**: the flat-growth one
  catches an N+1, and the literal catches somebody adding a fifth
  constant-cost aggregate, which the flat-growth one stays green through. The
  same second half was added to `EventIndexTest` in the same change.
- **`WaitingOnResolver` needed no change to be fed a preloaded collection**, and
  the endpoint asks it and takes what it says. Feature suppression is part of
  that calculation rather than a filter over the top of it (business logic 6 and
  21), so nothing here filters afterwards on an opinion of its own, and nothing
  re-guards `lost` and `cancelled`. The controller does exclude them from the
  query, which is not a second opinion: the resolver answers null for both, and
  this is the query declining to ask about rows whose answer is known.
- **A fourth `MoneyCast` currency trap, found by the query-count test.**
  `payments` has no currency column, so a payment fetched without its booking
  costs a query each. `PaymentsReceived` already joins `bookings` to filter the
  currency, so it selects `bookings.currency` into the row: the cast looks at
  the row's own `currency` attribute first and never reaches for the relation.
  Cheaper than eager loading the booking, and worth knowing as the other way out
  of this trap.
- **The attention block is capped at 100 and it is NOT naturally bounded.** It
  reads as the artist's open workload, so forty-ish, and that intuition is
  wrong: `artist_enquiry_cold` fires on every live enquiry nobody has touched
  for `cold_enquiry_days`, so an artist who does not open the app for a month
  has every one of them on the list. The cap is applied after the ordering, so
  what survives is the most urgent, and the tail dropped is `client_signature`
  and never an overdue balance.
- **Decision 217's precedence is the array's order**, and it matters more here
  than on the enquiries list because of the cap: the phone previews four rows,
  so an array grouped by party would put four of the artist's own rows at the
  top and an overdue balance could never reach the preview. That was a real bug,
  found by building the prototype. The order lives on `App\Enums\WaitingOn` as
  `precedence()` and `rank()`, beside the resolver's own list of checks, because
  they are the same decision applied to one booking and to many. Ties inside one
  value are oldest first, on whichever timestamp that value is about: sorting
  everything on `last_touched_at` would put the least overdue balance above the
  most overdue one.
- **A row's money is the BOOKING's, not one invoice's** (decision
  2026-09-06.2212). Schema 5.15 allows a second invoice to be raised manually,
  so a booking with two overdue balances is a supported state, and the row was
  always one per booking while its money was per invoice: in that state it named
  one of the two and the owed headline, being the sum of the rows, under-
  reported by the other. `outstanding_minor` is now the booking's total overdue
  balance, `invoice_total_minor` the sum of those invoices' totals, and `due_on`
  the earliest of their due dates, so "£540 of £1,200 · 16 days late" is a true
  sentence about the booking and the sixteen days is the oldest overdue invoice
  on it, which is the number an artist says out loud when chasing. The sums live
  on `App\Support\AttentionRow` and the controller reads the same methods for
  the headline, **so the headline and the sum of the rows agree by construction
  rather than by a test.** It also removes half of decision 2026-09-06.2112:
  "which of two invoices does this row name" stops being a question when the row
  names none of them, and `AttentionRow`'s invoices are therefore a
  collection rather than a model.
- **The row sends raw material and never a sentence or a day count.** The
  wording is British English in the app's locale file, and every "9 days late"
  is worked out at render with `differenceInCalendarDays`. `outstanding_minor`
  and `due_on` read against the balance or against the deposit and `waiting_on`
  says which, which is one question asked of a different part of one invoice
  rather than a field meaning two things. **No venue on a row**, which is a
  finding rather than an omission: it was on the money rows first and is what
  pushed a fifth row past the fold at 375px.
- **`party` is sent rather than parsed off the front of the value.** Same rule
  as `lost_side`: the party is a fact about the record and the heading beside it
  is wording. `App\Enums\WaitingParty` is its own enum and deliberately not
  `EndingSide`, which happens to have the same two values and means something
  else.
- **The money block is never removed by a feature toggle** (decision
  2026-09-06.1946, correcting an earlier reading of 21.2). What a toggle removes
  is named there as items from the *attention* block. A booking carries a price
  whether or not anybody raised an invoice for it, so the toggles take the cash
  half away one figure at a time: invoicing off nulls `owed_minor` and
  `outstanding`, and payment tracking off as well turns the period figure into
  booked value. **`basis` says which**, so the screen cannot draw "Received: £0"
  on an account that records no payments, and no figure is ever communicated by
  an absent key.
- **`snoozed_minor` is decision 27's own reason for existing.** That decision
  says an artist who can only stop the chasers by marking an invoice paid will
  mark it paid, and the earnings figures then quietly become wrong. The snooze
  is the honest escape hatch, and one that silently shrinks the headline teaches
  artists to distrust the headline instead. So the money a snooze took out of
  `owed_minor` is named once beside it. It reads the same live bookings the
  attention rows came from, so it costs no query and cannot count a lost
  booking. It totals invoices, and so does the headline now that a row's money
  is the booking's, so the two are the same shape: this is the money that would
  have been in the headline had nobody snoozed it.
- **A snoozed invoice is still overdue, and `Invoice` now says the two things
  separately.** `isPastDue()` is the date question, `isSnoozed()` is the pause,
  and `isOverdue()` is both, unchanged. Without the split, `OutstandingBalances`
  had to classify snoozed money as merely "due", which reports a fortnight-late
  balance as if it were not late. `outstanding.snoozed_minor` is therefore a
  **subset of `overdue_minor`, never a third bucket**: due plus overdue is the
  whole of outstanding, and the snoozed figure names the part of overdue nobody
  is being chased for. `balanceIsPastDue()` is the balance half of the same
  question, which is the half the owed headline is about.
- **All four periods come back at once**, computed from one query over the
  widest window and bucketed in PHP. The selector then works with no signal, and
  the endpoint's cost stops being something its caller sets. **Every period ends
  today**, so a wedding later this month is in `booked_ahead_minor` rather than
  in `this_month`: a period figure looks backwards and the as-of-today figures
  look forwards.
- **Under `booking_value`, a booking counts under its main day**, chosen by
  `ContactActivity::mainEvent()` so one booking cannot appear under two dates
  anywhere in the app. The three candidates answer three different questions:
  the main date is what was worked, `created_at` is what came in and
  `converted_at` is what was sold, and the count and the average change meaning
  with each. The main date wins because booked value is the accrual twin of cash
  received and has to look at the same window from the other side, so the two
  are comparable the day an artist switches payment tracking on. **A booking
  with no event belongs to no period and is in none of them**, which is a real
  row rather than an edge case.
- **All three period figures share one basis**, so the average always equals the
  value divided by the count. A cash figure beside an accrual count under one
  heading would be two answers to different questions with nothing saying so.
- **Money is filtered to the account currency and says so.** Schema section 8
  allows either that or grouping, and a headline cannot be an array.
  `excludes_other_currencies` is what stops the figure being a silent lie on an
  account with a job abroad.
- **`owed_minor` is summed before the cap**, from every `client_balance` row
  rather than the ones that survived it, and that is the resolution of a real
  collision between decision 2026-09-06.1954 and the cap. The figure is the size
  of the problem rather than the size of the visible list, and
  `meta.attention.truncated` is what tells the screen the rows under it are not
  all of them. `outstanding` is its own query and a different question: a
  booking with an invoice due next month is outstanding and waiting on nobody,
  so `overdue_minor` is usually larger than `owed_minor` because an overdue
  deposit is late money that is not a balance.
- **Six upcoming events**, where the screen draws three. Sending exactly three
  is how an endpoint becomes one screen's private API, and it leaves nothing
  behind when a same-day event passes. The unit is the event, so a trial and a
  wedding of one booking are two rows. `party_size` is a count and never words,
  null at nought for the reason the enquiry detail's own `party_size` gives.
  Travel is seconds and metres, null while schema 5.9 calls those columns unused
  and null as well when the artist has travel estimates switched off.
- **`meta.today` and `meta.timezone` earn their place**: every detail line is a
  day count the client computes, and a phone in another timezone computes a
  different one from the same instant than the server used to decide what is
  overdue.
- **`business_year` reads `account_settings.business_year_start_month` and
  `business_year_start_day`**, which already existed and default to 6 April. The
  only real requirement in `BusinessPeriods` is the 29 February clamp: Carbon
  overflows rather than refusing, so an artist on a 29 February business year
  would have it start on 1 March in three years out of four with nothing
  reporting it.
- The services, each owning one question: `App\Services\AttentionRows` (the
  precedence and the record each value is about), `App\Services\BusinessPeriods`
  (a calendar, not money), `App\Services\PaymentsReceived` (cash in, on the
  payment date), `App\Services\BookingValue` (what a set of bookings is worth,
  through `BookingPricing` rather than re-deriving a total). `OutstandingBalances`
  was **widened rather than twinned**: its new `total()` is the same rule as
  `for()` at a different scope, and an `OutstandingTotals` beside it would be two
  places to change one rule. The two shapes stay two, because a contact row shows
  one pill and a headline shows two figures.
- **Which values a real account can reach today: six of eight.**
  `artist_review` and `client_form` both need `intake_forms`, which is schema 7.4
  and designed rather than migrated, so decision 219 stands them down whatever
  the toggle says. The demo account reaches **five**: it has no agreement at
  `sent`, so `client_signature` is unreachable on it until the seeder changes.
