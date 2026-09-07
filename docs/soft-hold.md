# The soft hold, its length and its writer

`bookings.hold_expires_at` is business logic 5.1's soft hold and 5.3's real one.
Until `App\Services\SoftHold` existed nothing in the application wrote it, so
`artist_not_held` — **first in decision 217's precedence, above money, because
the date itself can be lost** — could only fire for rows a seeder set by hand.
The top row of the home screen's attention list was fiction.

- **`account_settings.hold_days`, defaulting to 14.** A column rather than a
  config entry, which is the opposite call from decision 218 and turns on a
  three-way distinction between those entries: `cold_enquiry_days` is a
  threshold on the app's own nagging with no settled home, `intake_available` is
  a fact about whether a table exists, and the three caps are endpoint costs.
  None is per-account by design and this one is, so config would be a
  placeholder for a decision already taken. It is also structurally identical to
  `deposit_due_days` and `balance_due_days_before`, and neither of those waited
  for a settings screen.
- **Fourteen days, and the demo fixtures had already assumed it.** The two rows
  that used to carry a hand-written hold were `converted_at` plus ten and minus
  five against conversions four and twenty days old, which is a fourteen-day
  hold written out longhand. It is deliberately **not** `cold_enquiry_days`,
  which is 21: one asks how long a date is being held, the other how long a
  conversation has been silent.
- **A service called explicitly, not a model event**, and `touchActivity()` is
  the deciding precedent rather than the hook's awkwardness: this codebase has
  already answered "how do you make sure every writer does a thing to a booking"
  once, with an explicit method plus a written rule. A hook here and a method
  there would be two answers to one question. **Every write path that changes a
  stage calls `SoftHold`**, the same rule `last_touched_at` carries.
- **One rule, three outcomes, expressed as a comparison of hold classes.**
  `App\Enums\HoldClass` is none, soft or firm, and `classOf()` is a `match`
  with no default, so a new `BookingStage` cannot be added without answering the
  question. The class going **up** starts a hold; a class of **none** clears it;
  anything else leaves it alone.
- **What falls out of that, and each is a decision.** Possible to Quoted does
  not restart the clock, because both hold softly. Converting to Provisional
  does, because 5.3 says the soft hold *becomes* a real one and without it a
  thirteen-day-old hold would say "not held" the morning after it was pencilled
  in. **Undoing a conversion leaves the hold exactly as it was**: a class that
  went down and is still holding, so clearing it would be false and restarting
  it would let an artist extend a hold for ever by converting and un-converting.
- **`confirmed`, `completed`, `closed` and `cancelled` hold nothing**, and that
  is a rule rather than a technicality: a date that is signed and deposited is
  not being held pending anything, so the column means precisely one thing.
  **Nothing writes those four stages today** — every occurrence in `app/` is a
  read, and `PATCH /api/enquiries` caps at `SETTABLE_STAGES` — so the branch is
  unreachable. It is written now because business logic 4.4 makes confirmation a
  transition triggered by a signature and a covered deposit, so whatever records
  those is the writer, and it calls `SoftHold` like every other one. Without it,
  the resolver's stage guard would be the only thing keeping `artist_not_held`
  off a signed wedding, which is one question answered in two places.
- **The hold never releases itself.** Nothing runs on a timer, and nothing moves
  a stage, clears a date or frees a Saturday when a hold lapses. Expiry changes
  what the app says and never what the data is: 5.2 is explicit that the app
  warns and never blocks, and an artist who lost a date because software decided
  a hold had lapsed has lost a wedding to a feature.
- **Storing the expiry rather than deriving it from `converted_at` plus the
  setting** is what makes a policy change non-retroactive for free: an artist
  who shortens her hold in March has not retrospectively expired a date she
  pencilled in February.
- **The seeder computes its holds through the same service.** A seeder that
  hand-sets a column the app computes is decisions 220 and 227 arriving a third
  time, and it would be worst here, where the whole point is that
  `artist_not_held` was fiction. `SoftHold` therefore takes the day the
  transition happened, defaulting to the account's today: computed from today it
  could only ever produce live holds, and a seeder wanting the lapsed case would
  be straight back to writing the column by hand. The demo now carries **seven
  computed holds, none hand-set**, two of them lapsed.
- **Two defects in the read side, found by building the write side.**
  `holdHasExpired()` used `isPast()` on a date cast, so a hold was dead from one
  second after midnight on its own expiry day — a fourteen-day hold lasting
  thirteen. And it compared against UTC, the last comparison of a stored date
  against the present still doing so after `Invoice::isOverdue()`,
  `OutstandingBalances` and `BusinessPeriods` all moved off it. It now takes the
  day to judge against, like `isOverdue()`, and `WaitingOnResolver` passes the
  one it already computes. **Neither could be reached by any existing test,
  because every fixture set the hold a day or more either side of today and none
  touched the boundary** — decision 197's lesson again, that a test which never
  touches the edge is documentation rather than a guard.
